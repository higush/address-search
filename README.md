# 住所検索機能

郵便番号の CSV を使い、インデックスファイルを作成して検索する機能
郵便番号 CSV：http://www.post.japanpost.jp/zipcode/dl/kogaki/zip/ken_all.zip

インデックスは N-Gram（N=2）を利用

## 利用方法

上記 zip ファイルを解凍して、address-search.php と同階層に保存しておく。（ファイル名：KEN_ALL.CSV）

```
php address-search.php 検索文字列
```

## 作成ファイル

`index.txt`：インデックスファイル  
`result.txt`：検索結果
