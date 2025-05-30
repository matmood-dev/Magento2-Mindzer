<?php

/**
 * This file is part of PDepend.
 *
 * PHP Version 5
 *
 * Copyright (c) 2008-2017 Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

namespace PDepend\Source\Language\PHP;

use PDepend\Source\AST\ASTCompilationUnit;
use PDepend\Source\Tokenizer\FullTokenizer;
use PDepend\Source\Tokenizer\Token;
use PDepend\Source\Tokenizer\Tokenizer;
use PDepend\Source\Tokenizer\Tokens;
use RuntimeException;

// @codeCoverageIgnoreStart

/**
 * Define PHP 5.4 __TRAIT__ token constant.
 */
if (!defined('T_TRAIT_C')) {
    define('T_TRAIT_C', 42000);
}

/**
 * Define PHP 5.4 'trait' token constant.
 */
if (!defined('T_TRAIT')) {
    define('T_TRAIT', 42001);
}

/**
 * Define PHP 5.4 'insteadof' token constant.
 */
if (!defined('T_INSTEADOF')) {
    define('T_INSTEADOF', 42002);
}

/**
 * Define PHP 5.3 __NAMESPACE__ token constant.
 */
if (!defined('T_NS_C')) {
    define('T_NS_C', 42003);
}

/**
 * Define PHP 5.3 'use' token constant
 */
if (!defined('T_USE')) {
    define('T_USE', 42004);
}

/**
 * Define PHP 5.3 'namespace' token constant.
 */
if (!defined('T_NAMESPACE')) {
    define('T_NAMESPACE', 42005);
}

/**
 * Define PHP 5.6 '...' token constant
 */
if (!defined('T_ELLIPSIS')) {
    define('T_ELLIPSIS', 42006);
}

/**
 * Define PHP 5.3's '__DIR__' token constant.
 */
if (!defined('T_DIR')) {
    define('T_DIR', 42006);
}

/**
 * Define PHP 5.3's 'T_GOTO' token constant.
 */
if (!defined('T_GOTO')) {
    define('T_GOTO', 42007);
}

/**
 * Define PHP 5.4's 'T_CALLABLE' token constant
 */
if (!defined('T_CALLABLE')) {
    define('T_CALLABLE', 42008);
}

/**
 * Define PHP 5.5's 'T_YIELD' token constant
 */
if (!defined('T_YIELD')) {
    define('T_YIELD', 42009);
}

/**
 * Define PHP 5,5's 'T_FINALLY' token constant
 */
if (!defined('T_FINALLY')) {
    define('T_FINALLY', 42010);
}

/**
 * Define character token that was removed in PHP 7
 */
if (!defined('T_CHARACTER')) {
    define('T_CHARACTER', 42011);
}

/**
 * Define bad character token that was removed in PHP 7
 */
if (!defined('T_BAD_CHARACTER')) {
    define('T_BAD_CHARACTER', 42012);
}

/**
 * Define PHP 7's '<=>' token constant
 */
if (!defined('T_SPACESHIP')) {
    define('T_SPACESHIP', 42013);
}

/**
 * Define PHP 7's '??' token constant
 */
if (!defined('T_COALESCE')) {
    define('T_COALESCE', 42014);
}

/**
 * Define PHP 7's '**' token constant
 */
if (!defined('T_POW')) {
    define('T_POW', 42015);
}

/**
 * Define PHP 7.4's fn arrow function keyword
 */
if (!defined('T_FN')) {
    define('T_FN', 42016);
}

/**
 * Define PHP 7.4's ??= operator
 */
if (!defined('T_COALESCE_EQUAL')) {
    define('T_COALESCE_EQUAL', 42017);
}

/**
 * Define PHP 8.0 tokens
 */
if (!defined('T_NAME_QUALIFIED')) {
    define('T_NAME_QUALIFIED', 42314);
}

if (!defined('T_NAME_FULLY_QUALIFIED')) {
    define('T_NAME_FULLY_QUALIFIED', 42312);
}

if (!defined('T_NAME_RELATIVE')) {
    define('T_NAME_RELATIVE', 42313);
}

if (!defined('T_MATCH')) {
    define('T_MATCH', 42341);
}

if (!defined('T_ATTRIBUTE')) {
    define('T_ATTRIBUTE', 42383);
}

if (!defined('T_NULLSAFE_OBJECT_OPERATOR')) {
    define('T_NULLSAFE_OBJECT_OPERATOR', 42387);
}

/**
 * Define PHP 8.1 tokens
 */
if (!defined('T_READONLY')) {
    define('T_READONLY', 42401);
}

if (!defined('T_ENUM')) {
    define('T_ENUM', 42372);
}

/** @codeCoverageIgnoreEnd */

/**
 * This tokenizer uses the internal {@link token_get_all()} function as token stream
 * generator.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class PHPTokenizerInternal implements FullTokenizer
{
    /** Internally used transition token. */
    private const T_ELLIPSIS = 23006;

    /**
     * Mapping between php internal tokens and php depend tokens.
     *
     * @var array<int, int>
     */
    protected static array $tokenMap = [
        T_AS => Tokens::T_AS,
        T_DO => Tokens::T_DO,
        T_IF => Tokens::T_IF,
        T_SL => Tokens::T_SL,
        T_SR => Tokens::T_SR,
        T_DEC => Tokens::T_DEC,
        T_FOR => Tokens::T_FOR,
        T_INC => Tokens::T_INC,
        T_NEW => Tokens::T_NEW,
        T_POW => Tokens::T_POW,
        T_TRY => Tokens::T_TRY,
        T_USE => Tokens::T_USE,
        T_VAR => Tokens::T_VAR,
        T_CASE => Tokens::T_CASE,
        T_ECHO => Tokens::T_ECHO,
        T_ELSE => Tokens::T_ELSE,
        T_EVAL => Tokens::T_EVAL,
        T_EXIT => Tokens::T_EXIT,
        T_FILE => Tokens::T_FILE,
        T_GOTO => Tokens::T_GOTO,
        T_LINE => Tokens::T_LINE,
        T_LIST => Tokens::T_LIST,
        T_NS_C => Tokens::T_NS_C,
        T_ARRAY => Tokens::T_ARRAY,
        T_BREAK => Tokens::T_BREAK,
        T_CLASS => Tokens::T_CLASS,
        T_CATCH => Tokens::T_CATCH,
        T_CLONE => Tokens::T_CLONE,
        T_CONST => Tokens::T_CONST,
        T_EMPTY => Tokens::T_EMPTY,
        T_ENDIF => Tokens::T_ENDIF,
        T_ENUM => Tokens::T_ENUM,
        T_FINAL => Tokens::T_FINAL,
        T_ISSET => Tokens::T_ISSET,
        T_PRINT => Tokens::T_PRINT,
        T_THROW => Tokens::T_THROW,
        T_TRAIT => Tokens::T_TRAIT,
        T_UNSET => Tokens::T_UNSET,
        T_WHILE => Tokens::T_WHILE,
        T_ENDFOR => Tokens::T_ENDFOR,
        T_ELSEIF => Tokens::T_ELSEIF,
        T_FUNC_C => Tokens::T_FUNC_C,
        T_GLOBAL => Tokens::T_GLOBAL,
        T_PUBLIC => Tokens::T_PUBLIC,
        T_RETURN => Tokens::T_RETURN,
        T_STATIC => Tokens::T_STATIC,
        T_STRING => Tokens::T_STRING,
        T_SWITCH => Tokens::T_SWITCH,
        T_CLASS_C => Tokens::T_CLASS_C,
        T_COMMENT => Tokens::T_COMMENT,
        T_DECLARE => Tokens::T_DECLARE,
        T_DEFAULT => Tokens::T_DEFAULT,
        T_DNUMBER => Tokens::T_DNUMBER,
        T_EXTENDS => Tokens::T_EXTENDS,
        T_FOREACH => Tokens::T_FOREACH,
        T_INCLUDE => Tokens::T_INCLUDE,
        T_LNUMBER => Tokens::T_LNUMBER,
        T_PRIVATE => Tokens::T_PRIVATE,
        T_REQUIRE => Tokens::T_REQUIRE,
        T_TRAIT_C => Tokens::T_TRAIT_C,
        T_ABSTRACT => Tokens::T_ABSTRACT,
        T_CALLABLE => Tokens::T_CALLABLE,
        T_ENDWHILE => Tokens::T_ENDWHILE,
        T_FUNCTION => Tokens::T_FUNCTION,
        T_INT_CAST => Tokens::T_INT_CAST,
        T_IS_EQUAL => Tokens::T_IS_EQUAL,
        T_OR_EQUAL => Tokens::T_OR_EQUAL,
        T_CONTINUE => Tokens::T_CONTINUE,
        T_METHOD_C => Tokens::T_METHOD_C,
        T_ELLIPSIS => Tokens::T_ELLIPSIS,
        T_OPEN_TAG => Tokens::T_OPEN_TAG,
        T_SL_EQUAL => Tokens::T_SL_EQUAL,
        T_SR_EQUAL => Tokens::T_SR_EQUAL,
        T_VARIABLE => Tokens::T_VARIABLE,
        T_ENDSWITCH => Tokens::T_ENDSWITCH,
        T_DIV_EQUAL => Tokens::T_DIV_EQUAL,
        T_AND_EQUAL => Tokens::T_AND_EQUAL,
        T_MOD_EQUAL => Tokens::T_MOD_EQUAL,
        T_MUL_EQUAL => Tokens::T_MUL_EQUAL,
        T_NAMESPACE => Tokens::T_NAMESPACE,
        T_NAME_FULLY_QUALIFIED => Tokens::T_STRING,
        T_NAME_QUALIFIED => Tokens::T_STRING,
        T_XOR_EQUAL => Tokens::T_XOR_EQUAL,
        T_INTERFACE => Tokens::T_INTERFACE,
        T_BOOL_CAST => Tokens::T_BOOL_CAST,
        T_CHARACTER => Tokens::T_CHARACTER,
        T_CLOSE_TAG => Tokens::T_CLOSE_TAG,
        T_INSTEADOF => Tokens::T_INSTEADOF,
        T_PROTECTED => Tokens::T_PROTECTED,
        T_SPACESHIP => Tokens::T_SPACESHIP,
        T_CURLY_OPEN => Tokens::T_CURLY_BRACE_OPEN,
        T_ENDFOREACH => Tokens::T_ENDFOREACH,
        T_ENDDECLARE => Tokens::T_ENDDECLARE,
        T_IMPLEMENTS => Tokens::T_IMPLEMENTS,
        T_NUM_STRING => Tokens::T_NUM_STRING,
        T_PLUS_EQUAL => Tokens::T_PLUS_EQUAL,
        T_ARRAY_CAST => Tokens::T_ARRAY_CAST,
        T_BOOLEAN_OR => Tokens::T_BOOLEAN_OR,
        T_INSTANCEOF => Tokens::T_INSTANCEOF,
        T_LOGICAL_OR => Tokens::T_LOGICAL_OR,
        T_UNSET_CAST => Tokens::T_UNSET_CAST,
        T_DOC_COMMENT => Tokens::T_DOC_COMMENT,
        T_END_HEREDOC => Tokens::T_END_HEREDOC,
        T_MINUS_EQUAL => Tokens::T_MINUS_EQUAL,
        T_BOOLEAN_AND => Tokens::T_BOOLEAN_AND,
        T_DOUBLE_CAST => Tokens::T_DOUBLE_CAST,
        T_INLINE_HTML => Tokens::T_INLINE_HTML,
        T_LOGICAL_AND => Tokens::T_LOGICAL_AND,
        T_LOGICAL_XOR => Tokens::T_LOGICAL_XOR,
        T_OBJECT_CAST => Tokens::T_OBJECT_CAST,
        T_STRING_CAST => Tokens::T_STRING_CAST,
        T_DOUBLE_ARROW => Tokens::T_DOUBLE_ARROW,
        T_INCLUDE_ONCE => Tokens::T_INCLUDE_ONCE,
        T_IS_IDENTICAL => Tokens::T_IS_IDENTICAL,
        T_DOUBLE_COLON => Tokens::T_DOUBLE_COLON,
        T_CONCAT_EQUAL => Tokens::T_CONCAT_EQUAL,
        T_IS_NOT_EQUAL => Tokens::T_IS_NOT_EQUAL,
        T_REQUIRE_ONCE => Tokens::T_REQUIRE_ONCE,
        T_BAD_CHARACTER => Tokens::T_BAD_CHARACTER,
        T_HALT_COMPILER => Tokens::T_HALT_COMPILER,
        T_START_HEREDOC => Tokens::T_START_HEREDOC,
        T_STRING_VARNAME => Tokens::T_STRING_VARNAME,
        T_OBJECT_OPERATOR => Tokens::T_OBJECT_OPERATOR,
        T_IS_NOT_IDENTICAL => Tokens::T_IS_NOT_IDENTICAL,
        T_OPEN_TAG_WITH_ECHO => Tokens::T_OPEN_TAG_WITH_ECHO,
        T_IS_GREATER_OR_EQUAL => Tokens::T_IS_GREATER_OR_EQUAL,
        T_IS_SMALLER_OR_EQUAL => Tokens::T_IS_SMALLER_OR_EQUAL,
        // T_PAAMAYIM_NEKUDOTAYIM      => Tokens::T_DOUBLE_COLON,
        T_ENCAPSED_AND_WHITESPACE => Tokens::T_ENCAPSED_AND_WHITESPACE,
        T_CONSTANT_ENCAPSED_STRING => Tokens::T_CONSTANT_ENCAPSED_STRING,
        T_YIELD => Tokens::T_YIELD,
        T_FINALLY => Tokens::T_FINALLY,
        T_COALESCE => Tokens::T_COALESCE,
        T_COALESCE_EQUAL => Tokens::T_COALESCE_EQUAL,
        // T_DOLLAR_OPEN_CURLY_BRACES  => Tokens::T_CURLY_BRACE_OPEN,
        T_FN => Tokens::T_FN,
        T_MATCH => Tokens::T_STRING,
        T_NULLSAFE_OBJECT_OPERATOR => Tokens::T_NULLSAFE_OBJECT_OPERATOR,
        T_READONLY => Tokens::T_READONLY,
    ];

    /**
     * Mapping between php internal text tokens an php depend numeric tokens.
     *
     * @var array<string, int>
     */
    protected static array $literalMap = [
        '@' => Tokens::T_AT,
        '/' => Tokens::T_DIV,
        '%' => Tokens::T_MOD,
        '*' => Tokens::T_MUL,
        '+' => Tokens::T_PLUS,
        ':' => Tokens::T_COLON,
        ',' => Tokens::T_COMMA,
        '=' => Tokens::T_EQUAL,
        '-' => Tokens::T_MINUS,
        '.' => Tokens::T_CONCAT,
        '$' => Tokens::T_DOLLAR,
        '`' => Tokens::T_BACKTICK,
        '\\' => Tokens::T_BACKSLASH,
        ';' => Tokens::T_SEMICOLON,
        '|' => Tokens::T_BITWISE_OR,
        '&' => Tokens::T_BITWISE_AND,
        '~' => Tokens::T_BITWISE_NOT,
        '^' => Tokens::T_BITWISE_XOR,
        '"' => Tokens::T_DOUBLE_QUOTE,
        '?' => Tokens::T_QUESTION_MARK,
        '!' => Tokens::T_EXCLAMATION_MARK,
        '{' => Tokens::T_CURLY_BRACE_OPEN,
        '}' => Tokens::T_CURLY_BRACE_CLOSE,
        '(' => Tokens::T_PARENTHESIS_OPEN,
        ')' => Tokens::T_PARENTHESIS_CLOSE,
        '<' => Tokens::T_ANGLE_BRACKET_OPEN,
        '>' => Tokens::T_ANGLE_BRACKET_CLOSE,
        '[' => Tokens::T_SQUARED_BRACKET_OPEN,
        ']' => Tokens::T_SQUARED_BRACKET_CLOSE,
        'use' => Tokens::T_USE,
        'goto' => Tokens::T_GOTO,
        'null' => Tokens::T_NULL,
        'self' => Tokens::T_SELF,
        'true' => Tokens::T_TRUE,
        'array' => Tokens::T_ARRAY,
        'false' => Tokens::T_FALSE,
        'trait' => Tokens::T_TRAIT,
        'yield' => Tokens::T_YIELD,
        'yield from' => Tokens::T_YIELD,
        'parent' => Tokens::T_PARENT,
        'finally' => Tokens::T_FINALLY,
        'callable' => Tokens::T_CALLABLE,
        'insteadof' => Tokens::T_INSTEADOF,
        'namespace' => Tokens::T_NAMESPACE,
        '__dir__' => Tokens::T_DIR,
        '__trait__' => Tokens::T_TRAIT_C,
        '__namespace__' => Tokens::T_NS_C,
        'readonly' => Tokens::T_READONLY,
    ];

    /** @var array<int, array<int, string>> */
    protected static array $substituteTokens = [
        T_DOLLAR_OPEN_CURLY_BRACES => ['$', '{'],
    ];

    /**
     * BuilderContext sensitive alternative mappings.
     *
     * Re-map based on the previous token
     *
     * @var array<int, array<int, int>>
     */
    protected static array $alternativeMap = [
        Tokens::T_USE => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_GOTO => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_NULL => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_SELF => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_TRUE => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_NAMESPACE => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_ARRAY => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
        ],

        Tokens::T_FALSE => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_NAMESPACE => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_NAMESPACE => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_DIR => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_NS_C => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_PARENT => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_FINALLY => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
        ],

        Tokens::T_CALLABLE => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
        ],

        Tokens::T_LIST => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
        ],

        Tokens::T_EMPTY => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
        ],

        Tokens::T_CLASS => [
            Tokens::T_DOUBLE_COLON => Tokens::T_CLASS_FQN,
        ],

        Tokens::T_READONLY => [
            Tokens::T_OBJECT_OPERATOR => Tokens::T_STRING,
            Tokens::T_FUNCTION => Tokens::T_STRING,
            Tokens::T_CONST => Tokens::T_STRING,
            Tokens::T_DOUBLE_COLON => Tokens::T_STRING,
        ],
    ];

    /** @var array<int, array<int, array<string, int|string>>> */
    protected static array $reductionMap = [
        Tokens::T_CONCAT => [
            Tokens::T_CONCAT => [
                'type' => self::T_ELLIPSIS,
                'image' => '..',
            ],
            self::T_ELLIPSIS => [
                'type' => Tokens::T_ELLIPSIS,
                'image' => '...',
            ],
        ],

        Tokens::T_ANGLE_BRACKET_CLOSE => [
            Tokens::T_IS_SMALLER_OR_EQUAL => [
                'type' => Tokens::T_SPACESHIP,
                'image' => '<=>',
            ],
        ],

        Tokens::T_QUESTION_MARK => [
            Tokens::T_QUESTION_MARK => [
                'type' => Tokens::T_COALESCE,
                'image' => '??',
            ],
        ],

        Tokens::T_MUL => [
            Tokens::T_MUL => [
                'type' => Tokens::T_POW,
                'image' => '**',
            ],
        ],
    ];

    /** The source file instance. */
    private ASTCompilationUnit $sourceFile;

    /** Count of all tokens. */
    private int $count = 0;

    /** Internal stream pointer index. */
    private int $index = 0;

    /**
     * Prepared token list.
     *
     * @var Token[]
     */
    private array $tokens;

    /** The next free identifier for unknown string tokens. */
    private int $unknownTokenID = 1000;

    /**
     * Returns the name of the source file.
     */
    public function getSourceFile(): ASTCompilationUnit
    {
        return $this->sourceFile;
    }

    /**
     * Sets a new php source file.
     *
     * @param string $sourceFile A php source file.
     */
    public function setSourceFile(string $sourceFile): void
    {
        unset($this->tokens);
        $this->sourceFile = new ASTCompilationUnit($sourceFile);
    }

    /**
     * Returns the previous token or null if there is no one yet.
     */
    public function prevToken(): ?Token
    {
        $this->tokenize();

        if ($this->index > 0 && $this->index < $this->count - 1 && $this->tokens) {
            return $this->tokens[$this->index - 1];
        }

        return null;
    }

    /**
     * Returns the current token or null if there is no more.
     */
    public function currentToken(): ?Token
    {
        $this->tokenize();

        if ($this->index < $this->count - 1 && $this->tokens) {
            return $this->tokens[$this->index];
        }

        return null;
    }

    /**
     * Returns the next token or {@link Tokenizer::T_EOF} if
     * there is no next token.
     */
    public function next(): int|Token
    {
        $this->tokenize();

        if ($this->index < $this->count && $this->tokens) {
            return $this->tokens[$this->index++];
        }

        return self::T_EOF;
    }

    /**
     * Returns the next token type or {@link Tokenizer::T_EOF} if
     * there is no next token.
     */
    public function peek(): int
    {
        $this->tokenize();

        if (isset($this->tokens[$this->index])) {
            return $this->tokens[$this->index]->type;
        }

        return self::T_EOF;
    }

    /**
     * Returns the token type at the given position relatively to the current position.
     *
     * @param int $shift positive or negative to apply to the current index.
     */
    public function peekAt(int $shift): int
    {
        $this->tokenize();

        if ($this->index < -$shift) {
            return self::T_BOF;
        }

        $index = $this->index + $shift;

        if (isset($this->tokens[$index])) {
            return $this->tokens[$index]->type;
        }

        return self::T_EOF;
    }

    /**
     * Returns the type of next token, after the current token. This method
     * ignores all comments between the current and the next token.
     *
     * @since  0.9.12
     */
    public function peekNext(): ?int
    {
        $this->tokenize();

        $offset = 0;

        do {
            $index = $this->index + ++$offset;

            if (!isset($this->tokens[$index])) {
                return null;
            }

            $type = $this->tokens[$index]->type;
        } while ($type === Tokens::T_COMMENT || $type === Tokens::T_DOC_COMMENT);

        return $type;
    }

    /**
     * Returns the previous token type or {@link Tokenizer::T_BOF}
     * if there is no previous token.
     */
    public function prev(): int
    {
        $this->tokenize();

        if ($this->index > 1 && $this->tokens) {
            return $this->tokens[$this->index - 2]->type;
        }

        return self::T_BOF;
    }

    /**
     * Split PHP 8 T_NAME(_FULLY)_QUALIFIED token into PHP 7 compatible tokens.
     *
     * @param array{int, string, int} $token
     * @return array<int, array<int, int|string>>
     */
    private function splitQualifiedNameToken(array $token): array
    {
        $result = [];

        foreach (explode('\\', $token[1]) as $index => $string) {
            if ($index) {
                $result[] = [
                    T_NS_SEPARATOR,
                    '\\',
                    $token[2],
                ];
            }

            if ($string !== '') {
                $result[] = [
                    T_STRING,
                    $string,
                    $token[2],
                ];
            }
        }

        return $result;
    }

    /**
     * Split PHP 8 T_NAME_RELATIVE token into PHP 7 compatible tokens.
     *
     * @param array<int|string> $token
     * @return array<int, array<int, int|string>>
     */
    private function splitRelativeNameToken(array $token, string $namespace): array
    {
        $result = [
            [
                T_NAMESPACE,
                'namespace',
                $token[2],
            ],
        ];

        foreach (explode('\\', $namespace) as $string) {
            $result[] = [
                T_NS_SEPARATOR,
                '\\',
                $token[2],
            ];

            if ($string !== '') {
                $result[] = [
                    T_STRING,
                    $string,
                    $token[2],
                ];
            }
        }

        return $result;
    }

    /**
     * This method takes an array of tokens returned by <b>token_get_all()</b>
     * and substitutes some of the tokens with those required by PDepend's
     * parser implementation.
     *
     * @param array<array{int, string, int}|string> $tokens Unprepared array of php tokens.
     * @return array<array<int, int|string>|string>
     */
    private function substituteTokens(array $tokens): array
    {
        $result = [];
        $attributeComment = null;
        $attributeCommentLine = null;
        $brackets = 0;

        foreach ($tokens as $index => $token) {
            $temp = (array) $token;
            $temp = $temp[0];

            if ($attributeComment) {
                if ($temp === '[') {
                    $brackets++;
                }

                if ($temp === ']') {
                    $brackets--;
                }

                if ($brackets <= 0) {
                    $result[] = [T_COMMENT, "$attributeComment */", (string) $attributeCommentLine];
                    $attributeComment = null;

                    continue;
                }

                $attributeComment .= is_array($token) ? $token[1] : $token;
            } elseif ($temp === T_ATTRIBUTE) {
                $attributeComment = '/* @';
                $attributeCommentLine = $token[2];
                $brackets = 1;
            } elseif (is_array($token) && ($temp === T_NAME_QUALIFIED || $temp === T_NAME_FULLY_QUALIFIED)) {
                foreach ($this->splitQualifiedNameToken($token) as $subToken) {
                    $result[] = $subToken;
                }
            } elseif (
                is_array($token)
                && $temp === T_NAME_RELATIVE
                && preg_match('/^namespace\\\\(.*)$/', $token[1], $match)
            ) {
                foreach ($this->splitRelativeNameToken($token, $match[1]) as $subToken) {
                    $result[] = $subToken;
                }
            } elseif (isset(self::$substituteTokens[$temp])) {
                foreach (self::$substituteTokens[$temp] as $token) {
                    $result[] = $token;
                }
            } elseif ($temp === '?' && isset($tokens[$index + 1][0]) && $tokens[$index + 1][0] === T_OBJECT_OPERATOR) {
                $tokens[$index + 1] = [
                    T_NULLSAFE_OBJECT_OPERATOR,
                    '?->',
                    1,
                ];

                continue;
            } else {
                $result[] = $token;
            }
        }

        return $result;
    }

    /**
     * Tokenizes the content of the source file with {@link token_get_all()} and
     * filters this token stream.
     *
     * @throws RuntimeException
     */
    private function tokenize(): void
    {
        if (isset($this->tokens)) {
            return;
        }

        $this->tokens = [];
        $this->index = 0;
        $this->count = 0;

        // No longer replacing short open tags since some want to track them.
        $source = $this->sourceFile->getSource();
        if ($source === null) {
            throw new RuntimeException('No source was given');
        }

        $tokens = $this->substituteTokens(token_get_all($source));

        // Is the current token between an opening and a closing php tag?
        $inTag = false;

        // The current line number
        $startLine = 1;

        $startColumn = 1;
        $endColumn = 1;

        $literalMap = self::$literalMap;
        $tokenMap = self::$tokenMap;

        // Previous found type
        $previousType = null;
        $previousStartColumn = 0;

        while ($token = current($tokens)) {
            $type = null;
            $image = null;

            if (is_string($token)) {
                $token = [null, $token];
            }

            if ($token[0] === T_OPEN_TAG || $token[0] === T_OPEN_TAG_WITH_ECHO) {
                $type = $tokenMap[$token[0]];
                $image = $token[1];
                $inTag = true;
            } elseif ($token[0] === T_CLOSE_TAG) {
                $type = $tokenMap[$token[0]];
                $image = $token[1];
                $inTag = false;
            } elseif (!$inTag) {
                $type = Tokens::T_NO_PHP;
                $image = $this->consumeNonePhpTokens($tokens);
            } elseif ($token[0] === T_WHITESPACE) {
                // Count newlines in token
                $lines = substr_count((string) $token[1], "\n");
                if ($lines === 0) {
                    $startColumn += strlen((string) $token[1]);
                } else {
                    $startColumn = strlen(
                        substr((string) $token[1], strrpos((string) $token[1], "\n") + 1),
                    ) + 1;
                }

                $startLine += $lines;
            } else {
                $value = strtolower((string) $token[1]);
                if (isset($literalMap[$value])) {
                    // Fetch literal type
                    $type = $literalMap[$value];
                    $image = $token[1];

                    // Check for a context sensitive alternative
                    if (isset(self::$alternativeMap[$type][$previousType])) {
                        $type = self::$alternativeMap[$type][$previousType];
                    }

                    if (isset(self::$reductionMap[$type][$previousType])) {
                        $image = self::$reductionMap[$type][$previousType]['image'];
                        $type = (int) self::$reductionMap[$type][$previousType]['type'];

                        $startColumn = $previousStartColumn;

                        array_pop($this->tokens);
                    }
                } elseif (isset($tokenMap[$token[0]])) {
                    $type = (int) $tokenMap[$token[0]];
                    // Check for a context sensitive alternative
                    if (isset(self::$alternativeMap[$type][$previousType])) {
                        $type = self::$alternativeMap[$type][$previousType];
                    }

                    $image = $token[1];
                } else {
                    // This should never happen
                    // @codeCoverageIgnoreStart
                    [$type, $image] = $this->generateUnknownToken((string) $token[1]);
                    // @codeCoverageIgnoreEnd
                }
            }

            if ($type && is_string($image)) {
                $rtrim = rtrim($image);
                $lines = substr_count($rtrim, "\n");
                if ($lines === 0) {
                    $endColumn = $startColumn + strlen($rtrim) - 1;
                } else {
                    $endColumn = strlen(
                        substr($rtrim, strrpos($rtrim, "\n") + 1),
                    );
                }

                $endLine = $startLine + $lines;

                $token = new Token($type, $rtrim, $startLine, $endLine, $startColumn, $endColumn);

                // Store token in internal list
                $this->tokens[] = $token;

                // Store previous start column
                $previousStartColumn = $startColumn;

                // Count newlines in token
                $lines = substr_count($image, "\n");
                if ($lines === 0) {
                    $startColumn += strlen($image);
                } else {
                    $startColumn = strlen(
                        substr($image, strrpos($image, "\n") + 1),
                    ) + 1;
                }

                $startLine += $lines;

                // Store current type
                if ($type !== Tokens::T_COMMENT && $type !== Tokens::T_DOC_COMMENT) {
                    $previousType = $type;
                }
            }

            next($tokens);
        }

        $this->count = count($this->tokens);
    }

    /**
     * This method fetches all tokens until an opening php tag was found and it
     * returns the collected content. The returned value will be null if there
     * was no none php token.
     *
     * @param array<array<int, int|string>|string> $tokens Reference to the current token stream.
     */
    private function consumeNonePhpTokens(array &$tokens): ?string
    {
        // The collected token content
        $content = null;

        // Fetch current token
        $token = (array) current($tokens);

        // Skipp all non open tags
        while (
            $token[0] !== T_OPEN_TAG_WITH_ECHO &&
               $token[0] !== T_OPEN_TAG &&
               $token[0] !== false
        ) {
            $content .= ($token[1] ?? $token[0]);

            $token = (array) next($tokens);
        }

        // Set internal pointer one back when there was at least one none php token
        if ($token[0] !== false) {
            prev($tokens);
        }

        return $content;
    }

    /**
     * Generates a dummy/temp token for unknown string literals.
     *
     * @param string $token The unknown string token.
     * @return array{int, string}
     */
    private function generateUnknownToken(string $token): array
    {
        return [$this->unknownTokenID++, $token];
    }
}
