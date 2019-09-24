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

# ベンチマーク

後ほど調査して追記します。

# テスト

```bash
phpunit -d --colors tests
```