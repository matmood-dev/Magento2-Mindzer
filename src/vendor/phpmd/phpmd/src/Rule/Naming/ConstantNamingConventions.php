<?php

/**
 * This file is part of PHP Mess Detector.
 *
 * Copyright (c) Manuel Pichler <mapi@phpmd.org>.
 * All rights reserved.
 *
 * Licensed under BSD License
 * For full copyright and license information, please see the LICENSE file.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Manuel Pichler <mapi@phpmd.org>
 * @copyright Manuel Pichler. All rights reserved.
 * @license https://opensource.org/licenses/bsd-license.php BSD License
 * @link http://phpmd.org/
 */

namespace PHPMD\Rule\Naming;

use PDepend\Source\AST\ASTConstantDeclarator;
use PHPMD\AbstractNode;
use PHPMD\AbstractRule;
use PHPMD\Rule\ClassAware;
use PHPMD\Rule\EnumAware;
use PHPMD\Rule\InterfaceAware;
use PHPMD\Rule\TraitAware;

/**
 * This rule detects class/interface constants that do not follow the upper
 * case convention.
 */
final class ConstantNamingConventions extends AbstractRule implements ClassAware, EnumAware, InterfaceAware, TraitAware
{
    /**
     * Extracts all constant declarations from the given node and tests that
     * the image only contains upper case characters.
     */
    public function apply(AbstractNode $node): void
    {
        foreach ($node->findChildrenOfType(ASTConstantDeclarator::class) as $declarator) {
            if ($declarator->getImage() !== strtoupper($declarator->getImage())) {
                $this->addViolation($declarator, [$declarator->getImage()]);
            }
        }
    }
}
