# このプロジェクトは

ApacheとPHPを利用したWebP対応支援ツールです。

Webサーバー側で、WebPフォーマット版のファイルの有無・そのファイル更新時間・対応ブラウザを判別して、WebP画像と従来のフォーマットの画像を振り分け配信を行います。

* Apache Webサーバーを対象にします。
* RewriteモジュールとPHPを利用します。
  * PHP 7.2で動作確認しました。
  * 動作確認はしていませんが、PHP 5.xでも動作するように意識して記述しています。
* 従来のフォーマットで画像を作成し、その同一ディレクトリにWebP画像ファイルを設置する前提です。
  * 例として、`/path/to/image.jpg`に対して`/path/to/image.jpg.webp`ファイルを用意します。
* 

# 解決する問題

バッチ処理により、Webサーバー上で従来フォーマットのWebPフォーマット版を作成し、ApacheのRewriteモジュールにより対応ブラウザへの振り分けを行うのは、WebP対応においてポピュラーな方法です。

しかしバッチ処理とRewriteモジュールだけによる振り分けでは、従来フォーマットによるオリジナル画像が同名で変更されたときにWebP画像との差分が生じます。

1. `sample.jpg`をアップロード
  * WebP対応ブラウザも非対応ブラウザも`sample.jpg`を閲覧
2. スクリプトが`sample.jpg.webp`を生成
  * WebP対応ブラウザは`sample.jpg.webp`、非対応ブラウザは`sample.jpg`を閲覧
3. `sample.jpg`の内容を変更して上書きアップロード
  * WebP非対応ブラウザは新しい`sample.jpg`を閲覧
  * WebP対応ブラウザは古い`sample.jpg.webp`を閲覧(ブラウザによる差分発生)

もしWebPファイルの有無だけでなく、`sample.jpg`の更新日が`sample.jpg.webp`より新しい場合、すべてのブラウザに新しい`sample.jpg`を配信できます。

4. `sample.jpg`が`sample.jpg.webp`より新しい場合は`sample.jpg`を優先配信
  * WebP非対応ブラウザは新しい`sample.jpg`を閲覧
  * WebP対応ブラウザも新しい`sample.jpg`を閲覧(差分解消)
5. スクリプトが`sample.jpg`から`sample.jpg.webp`を再度生成
  * WebP非対応ブラウザは新しい`sample.jpg`を閲覧
  * WebP対応ブラウザは新しい`sample.jpg.webp`を閲覧

なお、Apache 2.5から`<If>`ディレクティブでファイル更新日の判定ができる見込みです。

# 設置方法

1. 画像を配置している最上位のディレクトリに`webp-rewrite-image.php`ファイルをアップロードします。
2. `.htaccess-example`から`.htaccess`を複製します。
3. `.htaccess`の`webp-rewrite-image.php`へのパスを配置したディレクトリに合わせて変更します。
  * 下記の例を参考ください。
4. `.htaccess`を`webp-rewrite-image.php`と同じディレクトリにアップロードします。

例えば、設置ディレクトリが`/img/`の場合、次のようにパスを変更します。

```apache
RewriteEngine On
RewriteRule .(jpe?g|png|gif)$ /img/rewrite-image.php [NC]
```

# 設定変更

`webp-rewrite-image.php`の次の箇所は自由に書き換えて利用ください。

* `$SuccessCache` 正常なレスポンスのヘッダに記述するキャッシュ期間。デフォルト7日間です。
* `$ErrorCache` エラーレスポンスのヘッダに記述するキャッシュ期間。デフォルト10分間です。
* `$SuccessDefaultHeaders` 正常なレスポンスのデフォルトヘッダ。
* `$ErrorDefaultHeaders` エラーレスポンスのデフォルトヘッダ。

```php
class Config
{
  public static $SuccessCache = 60 * 60 * 24 * 7; // 7days
  public static $ErrorCache = 10 * 60; // 10 minuts
  
  public static $SuccessDefaultHeaders = array(
    // 'X-My-Header' => 'the value',
  );
  
  public static $ErrorDefaultHeaders = array(
    // 'X-My-Header' => 'the value',
  );
```

# ベンチマーク

簡単なスクリプトを作成して(`benchmark/run.sh`)、計測した一例です。

体感速度に差がでるほどの遅延ではないと言えます。

* MacBook Pro 15
* Intel core i7 3.1GHz 4コア
* メモリ 16 GB
* 同一マシン内でWebサーバーと`ab`を実行
* 50同時リクエストで10000リクエストを実行 `ab -c 50 -n 10000`
* 単位はms

| ブラウザ    | メソッド | 振り分け方式    | 応答時間(90%) | 応答時間(95%) | 応答時間(99%) | 応答時間(100%・最長) |
|---------|------|-----------|-----------|-----------|-----------|---------------|
| WebP対応  | HEAD | .htaccess | 5         | 6         | 8         | 18            |
| WebP対応  | HEAD | PHP       | 11        | 13        | 3661      | 19610         |
| WebP対応  | GET  | .htaccess | 4         | 4         | 5         | 6             |
| WebP対応  | GET  | PHP       | 12        | 14        | 13198     | 13213         |
| WebP非対応 | HEAD | .htaccess | 4         | 5         | 6         | 13173         |
| WebP非対応 | HEAD | PHP       | 11        | 12        | 3543      | 13181         |
| WebP非対応 | GET  | .htaccess | 4         | 4         | 5         | 13162         |
| WebP非対応 | GET  | PHP       | 11        | 13        | 185       | 7146          |

* 上記環境で、PHPによるWebP画像の振り分けは`.htaccess`のみによる振り分けに対し、6〜8ms(比率としては2〜3倍)遅い結果となりました。
* PHPによる振り分けではリソースに負荷がかかるため99パーセンタイルから遅延が目立ちます。


# テスト

```bash
phpunit -d --colors tests
```