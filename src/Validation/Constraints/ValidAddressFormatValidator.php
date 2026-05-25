<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\Validation\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidAddressFormatValidator extends ConstraintValidator
{
    /**
     * @param object|null $checker Optional core AddressFormatCheckerInterface instance.
     *                             Type-hinted as object so this validator class is
     *                             autoloadable on PrestaShop versions where the
     *                             AddressFormatCheckerInterface symbol does not exist
     *                             (pre-9.2). Service wiring uses '@?…' so the argument
     *                             resolves to null when the alias is missing.
     */
    public function __construct(private readonly ?object $checker = null)
    {
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAddressFormat) {
            throw new UnexpectedTypeException($constraint, ValidAddressFormat::class);
        }

        // No core checker available (pre-9.2) — skip validation entirely so
        // the field behaves as it did before the constraint was introduced.
        if (null === $this->checker) {
            return;
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $errors = $this->checker->validate($value);
        foreach ($errors as $error) {
            $this->context->buildViolation($error)->addViolation();
        }
    }
}
