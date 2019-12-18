<?php

/***
 * ・郵便番号のCSVをデータソースとし、住所レコードのインデックスファイルを作成する機能
 *  (http://www.post.japanpost.jp/zipcode/dl/kogaki/zip/ken_all.zip)
 * ・文字列中の文字をすべて含む住所レコードを上記で作成したインデックスを用いて検索し出力する機能。
 *
 * 引数1：検索ワード
 ***/



// 初期変数
$csv_file = "KEN_ALL.CSV";
$csv_utf8 =  "KEN_ALL_utf8.CSV";
$index_file = "index.txt";
$result_file = "result.txt";

// 事前準備（不要ファイルの削除）
exec("rm -rf $csv_utf8");
exec("rm -rf $index_file");
exec("rm -rf $result_file");

// 引数データ
if ($argc > 1) {
  $input_string = $argv[1];
} else {
  $input_string = "";
}

if ($input_string === "") {
  echo "検索文字を入力してください\n";
  return;
}

class Bigram
{
  /**
   * 文字列を登録・検索用バイグラムに変換する
   *
   * @param string|null $string
   * @param boolean $for_search_flag
   *     false:DB保存時に使用する変換方法 あいう→あい いう う
   *     true:検索時に使用する変換方法 あいう→あい いう　1文字の場合は空文字を返します（そもそも2文字以上で検索しないとヒットしないので構わない）
   * @return string|null
   */
  public function convert_to_bigram(string $string = null, bool $for_search_flag = false)
  {
    if (is_null($string)) {
      return null;
    }

    $string = str_replace(array(" ", "　", "\r", "\n", "\t"), "", $string);
    $string = strip_tags($string);
    $character_list = preg_split("//u", $string, -1, PREG_SPLIT_NO_EMPTY); // 1文字づつ配列に分ける

    $bigram = ''; // バイグラム

    $glue = '';
    foreach ($character_list as $index => $character) {
      if (isset($character_list[$index + 1])) {
        $bigram .= $glue . $character . $character_list[$index + 1];
      } else {
        if ($for_search_flag === false) {
          $bigram .= $glue . $character;
        }
      }
      $glue = ' ';
    }

    return $bigram;
  }
}


// ファイルの加工
// CSVファイルの文字コードをSJIS→UTF-8に変換して保存
file_put_contents($csv_utf8, mb_convert_encoding(file_get_contents($csv_file), 'UTF-8', 'SJIS-win'));

$file = new SplFileObject($csv_utf8);
$file->setFlags(SplFileObject::READ_CSV);
$records = array();

// CSVデータを配列に格納
foreach ($file as $line) {
  //終端の空行を除く
  if (!is_null($line[0])) {
    if (array_key_exists($line[2], $records)) {
      // 同一郵便番号が存在する場合、住所の追加処理
      $records[$line[2]][3] = $records[$line[2]][3]  . $line[8];
    } else {
      // 新規
      $records[$line[2]][0] = $line[2];
      $records[$line[2]][1] = $line[6];
      $records[$line[2]][2] = $line[7];
      $records[$line[2]][3] = $line[8];
    }
  }
}

// インデックスファイル作成
$index = array();
$bigram = new Bigram();

foreach ($records as $key => $value) {
  $post_no = $key;
  $prefecture = $value[1];
  $city = $value[2];
  $number = $value[3];
  $address = $prefecture . $city . $number;

  // 住所をバイグラムで分割
  $tmp = explode(" ", $bigram->convert_to_bigram($address, true));

  // 分割した文字列をindex配列に格納
  // 対象のindex配列がすでにある場合、カンマ区切りで郵便番号を追加セットする
  for ($j = 0; $j < count($tmp); $j++) {
    if (isset($index[$tmp[$j]])) {
      // 追加
      $index[$tmp[$j]] = $index[$tmp[$j]] . "," . $post_no;
    } else {
      // 新規
      $index[$tmp[$j]] = $post_no;
    }
  }
}

// インデックスファイルの出力
foreach ($index as $key => $value) {
  file_put_contents($index_file, $key . "," . $value . "\n", FILE_APPEND);
}

// 検索機能
// 検索ワード（引数）をバイグラムで分割
$search = explode(" ", $bigram->convert_to_bigram($input_string, true));
$address_array_tmp = array();

// 分割した検索ワードがインデックス配列に存在するかチェック
foreach ($search as $key => $value) {
  if (array_key_exists($value, $index)) {
    // インデックス配列に存在する場合、対象郵便番号を取得。カンマ区切りなので、それぞれ配列に格納
    $tmp = explode(",", $index[$value]);
    for ($i = 0; $i < count($tmp); $i++) {
      $address_array_tmp[] = $tmp[$i];
    }
  } else {
    continue;
  }
}

// 配列内の重複キーを削除（＝郵便番号をユニークにする）
$address_array = array_unique($address_array_tmp);

if (count($address_array) < 1) {
  // インデックス配列でヒットしなかった場合
  echo "検索対象のデータがありません\n";
} else {
  // インデックス配列でヒットした場合
  foreach ($address_array as $key => $value) {
    if (array_key_exists($value, $records)) {
      // 念の為、各要素をダブルクォーテーションで囲う
      $result = implode(",", preg_replace('/^(.*?)$/', '"$1"', $records[$value]));
      echo $result . "\n";

      // 結果をファイル出力
      file_put_contents($result_file, $result . "\n", FILE_APPEND);
    }
  }
}
