<?php
/**
 * Copyright since 2026 200IQ Labs and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Qamera AI
 * @copyright Since 2026 200IQ Labs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
/*
 * PrestaShop's own coding standard, with one rule turned off.
 *
 *   composer require --dev prestashop/php-dev-tools friendsofphp/php-cs-fixer
 *   vendor/bin/php-cs-fixer fix --dry-run --diff
 *
 * `blank_line_after_opening_tag` is disabled because PrestaShop's two tools
 * disagree about it: the fixer inserts a blank line between `<?php` and the
 * licence header, and the Addons validator then rejects the file with "There
 * must be no blank lines before the file comment" (report of 2026-08-26, 11
 * files). PrestaShop's own shipped modules follow the validator, not the
 * fixer. The validator is the gate, so it wins.
 */
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/qameraai');

// PrestaShop's Config hard-codes getRules() and ignores setRules(), so the
// rule set is taken from it and handed to a plain Config instead of trying to
// override it in place.
$rules = (new PrestaShop\CodingStandards\CsFixer\Config())->getRules();
$rules['blank_line_after_opening_tag'] = false;

return (new PhpCsFixer\Config('PrestaShop coding standard (Qamera)'))
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules($rules)
    ->setFinder($finder);
