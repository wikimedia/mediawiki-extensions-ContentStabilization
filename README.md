# ContentStabilization

## Installation
Execute

    composer require hallowelt/contentstabilization dev-REL1_35
within MediaWiki root or add `hallowelt/contentstabilization` to the
`composer.json` file of your project

## Activation
Add

    wfLoadExtension( 'ContentStabilization' );
to your `LocalSettings.php` or the appropriate `settings.d/` file.