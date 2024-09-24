<?php
session_start();

// 認証されていない場合はログインページにリダイレクト
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

// ログアウト処理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 初期設定
$directory = isset($_GET['dir']) ? $_GET['dir'] : '.';
$sort_key = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// ディレクトリが安全か確認（ディレクトリトラバーサル攻撃を防ぐ）
$realBase = realpath('.');
$realUserPath = realpath($directory);
if ($realUserPath === false || strpos($realUserPath, $realBase) !== 0) {
    die("不正なディレクトリへのアクセス");
}

// ディレクトリ内のフォルダとファイルを取得
$allItems = scandir($directory);

// フォルダとファイルを分離
$folders = [];
$files = [];
foreach ($allItems as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    $fullPath = $directory . DIRECTORY_SEPARATOR . $item;
    if (is_dir($fullPath)) {
        $folders[] = $item;
    } else {
        $files[] = $item;
    }
}

// 検索フィルタ
if ($search !== '') {
    $folders = array_filter($folders, function($folder) use ($search) {
        return stripos($folder, $search) !== false;
    });
    $files = array_filter($files, function($file) use ($search) {
        return stripos($file, $search) !== false;
    });
}

// ソート関数
function sort_items(&$items, $directory, $sort_key, $order) {
    usort($items, function($a, $b) use ($directory, $sort_key, $order) {
        $a_fullPath = $directory . DIRECTORY_SEPARATOR . $a;
        $b_fullPath = $directory . DIRECTORY_SEPARATOR . $b;
        
        // 名前順
        if ($sort_key === 'name') {
            $result = strcasecmp($a, $b);
        }
        // 更新日順
        elseif ($sort_key === 'date') {
            $result = filemtime($a_fullPath) - filemtime($b_fullPath);
        }
        // 作成日順
        elseif ($sort_key === 'created') {
            $result = filectime($a_fullPath) - filectime($b_fullPath);
        }
        // サイズ順 (ファイルのみ)
        elseif ($sort_key === 'size') {
            if (is_file($a_fullPath) && is_file($b_fullPath)) {
                $result = filesize($a_fullPath) - filesize($b_fullPath);
            } else {
                return 0; // フォルダはサイズに影響しない
            }
        }
        // 種類順（拡張子順）
        elseif ($sort_key === 'type') {
            $result = strcasecmp(pathinfo($a_fullPath, PATHINFO_EXTENSION), pathinfo($b_fullPath, PATHINFO_EXTENSION));
        } else {
            $result = 0;
        }

        // 昇順・降順の切り替え
        return $order === 'asc' ? $result : -$result;
    });
}

// フォルダとファイルのソートを実施
sort_items($folders, $directory, $sort_key, $order);
sort_items($files, $directory, $sort_key, $order);

// ソート順の切り替えリンクのための逆順を設定
$reverse_order = $order === 'asc' ? 'desc' : 'asc';

// ヘッダーのHTML
echo '<!DOCTYPE html>';
echo '<html lang="ja">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>ディレクトリ一覧</title>';
echo '<style>';
echo 'body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }';
echo '.explorer { max-width: 800px; margin: 0 auto; border: 1px solid #ccc; background-color: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }';
echo '.explorer h1 { font-size: 24px; margin-bottom: 20px; }';
echo '.explorer a { text-decoration: none; color: #0066cc; display: block; padding: 5px 10px; margin: 5px 0; background-color: #f9f9f9; border-radius: 4px; }';
echo '.explorer a:hover { background-color: #e9e9e9; }';
echo '.logout { position: absolute; top: 20px; right: 20px; }';
echo 'button { padding: 10px 20px; background-color: #0066cc; color: white; border: none; border-radius: 5px; cursor: pointer; }';
echo 'button:hover { background-color: #005bb5; }';
echo 'table { width: 100%; border-collapse: collapse; }';
echo 'th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }';
echo 'th a { color: #0066cc; text-decoration: none; }';
echo '</style>';

// ページを5秒ごとにリロードするためのJavaScript
echo '<script>';
echo 'setTimeout(function() { window.location.reload(); }, 5000);'; // 5秒後にページをリロード
echo '</script>';

echo '</head>';
echo '<body>';

// ログアウトボタンを右上に表示
echo '<div class="logout">';
echo '<form action="directory.php" method="get">';
echo '<button type="submit" name="logout">ログアウト</button>';
echo '</form>';
echo '</div>';

echo '<div class="explorer">';
echo '<h1>フォルダ: ' . htmlspecialchars($directory) . '</h1>';

// 検索フォーム
echo '<div class="actions">';
echo '<form method="GET">';
echo '<input type="hidden" name="dir" value="' . htmlspecialchars($directory) . '">';
echo '<input type="text" name="search" placeholder="検索..." value="' . htmlspecialchars($search) . '">';
echo '<input type="submit" value="検索">';
echo '</form>';
echo '</div>';

// ソートヘッダー
echo '<table>';
echo '<tr>';
echo '<th><a href="?dir=' . urlencode($directory) . '&sort=name&order=' . $reverse_order . '">名前</a></th>';
echo '<th><a href="?dir=' . urlencode($directory) . '&sort=date&order=' . $reverse_order . '">更新日</a></th>';
echo '<th><a href="?dir=' . urlencode($directory) . '&sort=created&order=' . $reverse_order . '">作成日</a></th>';
echo '<th><a href="?dir=' . urlencode($directory) . '&sort=size&order=' . $reverse_order . '">サイズ</a></th>';
echo '<th><a href="?dir=' . urlencode($directory) . '&sort=type&order=' . $reverse_order . '">種類</a></th>';
echo '</tr>';

// フォルダ表示
foreach ($folders as $folder) {
    echo '<tr>';
    echo '<td><a href="?dir=' . urlencode($directory . DIRECTORY_SEPARATOR . $folder) . '">📁 ' . htmlspecialchars($folder) . '</a></td>';
    echo '<td>-</td><td>-</td><td>-</td><td>-</td>';
    echo '</tr>';
}

// ファイル表示
foreach ($files as $file) {
    $fullPath = $directory . DIRECTORY_SEPARATOR . $file;
    echo '<tr>';
    echo '<td><a href="' . urlencode($fullPath) . '" target="_blank">📄 ' . htmlspecialchars($file) . '</a></td>';
    echo '<td>' . date("Y/m/d H:i:s", filemtime($fullPath)) . '</td>';
    echo '<td>' . date("Y/m/d H:i:s", filectime($fullPath)) . '</td>';
    echo '<td>' . (is_file($fullPath) ? filesize($fullPath) . ' bytes' : '-') . '</td>';
    echo '<td>' . strtoupper(pathinfo($fullPath, PATHINFO_EXTENSION)) . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';
echo '</body>';
echo '</html>';
