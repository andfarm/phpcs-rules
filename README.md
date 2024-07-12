Common Ground rules for PHP-CS-Fixer
====================================

Provides some helpful, opinionated [PHP-CS-Fixer][phpcsf] rules aimed at
normalizing code.

Currently, the only rule included is `wrap_block_comments`.

To use these rules, run:

```shell
composer require --dev commonground/phpcs-rules
```

Then add the following to your `.php-cs-fixer.dist.php`:

```php
require("vendor/autoload.php"); // if not already present

$cfg->registerCustomFixers([
    new \CommonGround\PhpCsFixer\WrapBlockCommentFixer(),
]);
```

wrap_block_comments
-------------------

wrap_block_comments wraps multi-line block comments (including docblocks)) to a
configurable maximum line length, defaulting to 80 columns. See the doc comments
for this fixer for details.

License
-------

The CommonGround logger, as with all Common Ground components, is MIT licensed.
See LICENSE for the full text.


[phpcsf]: https://packagist.org/packages/friendsofphp/php-cs-fixer
