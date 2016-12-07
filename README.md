# Mage Shell Hero
Magento 1.x shell enhancement commands. Pre-release.

Install Mage Shell Hero
=

1. Modify composer.json `extra:{}` to include:

        "installer-paths": {
            "lib/n98-magerun/modules/{$name}": [
                "etre/mage-shell-hero"
            ]
        },
   Do not change this location. magerun inherits Mage Shell Hero commands from th *lib/n98-magerun/modules` directory.
2. Execute `composer require etre/mage-patch-hero`

You should see the package installed at `[magento root]/lib/n98-magerun/modules/mage-shell-hero`.

How to use
=

If you used the install path from the installation instructions above, cd into `shell/etre/mage-shell-hero`. 

To see a list of commands execute `php magento`.
