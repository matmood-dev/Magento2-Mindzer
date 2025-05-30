<?php

declare(strict_types=1);

namespace Laminas\View\Model;

use ArrayAccess;
use ArrayIterator;
use Laminas\Stdlib\ArrayUtils;
use Laminas\View\Exception;
use Laminas\View\Variables as ViewVariables;
use ReturnTypeWillChange; // phpcs:ignore
use Traversable;

use function array_key_exists;
use function array_merge;
use function count;
use function get_debug_type;
use function gettype;
use function is_array;
use function is_object;
use function sprintf;

/** @final */
class ViewModel implements ModelInterface, ClearableModelInterface, RetrievableChildrenInterface
{
    /**
     * What variable a parent model should capture this model to
     *
     * @var string
     */
    protected $captureTo = 'content';

    /**
     * Child models
     *
     * @var list<ModelInterface>
     */
    protected $children = [];

    /**
     * Renderer options
     *
     * @var array<string, mixed>
     */
    protected $options = [];

    /**
     * Template to use when rendering this model
     *
     * @var string
     */
    protected $template = '';

    /**
     * Is this a standalone, or terminal, model?
     *
     * @var bool
     */
    protected $terminate = false;

    /**
     * View variables
     *
     * @var array|ArrayAccess|Traversable
     * @psalm-var array|ArrayAccess&Traversable
     */
    protected $variables = [];

    /**
     * Is this append to child  with the same capture?
     *
     * @var bool
     */
    protected $append = false;

    /**
     * Constructor
     *
     * @param  null|array<string, mixed>|Traversable<string, mixed>|ArrayAccess<string, mixed> $variables
     * @param  null|array<string, mixed>|Traversable<string, mixed> $options
     */
    public function __construct($variables = null, $options = null)
    {
        if (null === $variables) {
            $variables = new ViewVariables();
        }

        // Initializing the variables container
        $this->setVariables($variables, true);

        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    /**
     * Property overloading: set variable value
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setVariable($name, $value);
    }

    /**
     * Property overloading: get variable value
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        if (! $this->__isset($name)) {
            return;
        }

        $variables = $this->getVariables();
        return $variables[$name];
    }

    /**
     * Property overloading: do we have the requested variable value?
     *
     * @param  string $name
     * @return bool
     */
    public function __isset($name)
    {
        $variables = $this->getVariables();
        return isset($variables[$name]);
    }

    /**
     * Property overloading: unset the requested variable
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        if (! $this->__isset($name)) {
            return;
        }

        unset($this->variables[$name]);
    }

    /**
     * Called after this view model is cloned.
     *
     * Clones $variables property so changes done to variables in the new
     * instance don't change the current one.
     *
     * @return void
     */
    public function __clone()
    {
        if (is_object($this->variables)) {
            $this->variables = clone $this->variables;
        }
    }

    /**
     * Set a single option
     *
     * @param  string $name
     * @param  mixed $value
     * @return ViewModel
     */
    public function setOption($name, $value)
    {
        $this->options[(string) $name] = $value;
        return $this;
    }

    /**
     * Get a single option
     *
     * @param  string       $name           The option to get.
     * @param  mixed|null   $default        (optional) A default value if the option is not yet set.
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        $name = (string) $name;
        return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * Set renderer options/hints en masse
     *
     * @param array<string, mixed>|Traversable<string, mixed> $options
     * @throws Exception\InvalidArgumentException
     * @return ViewModel
     */
    public function setOptions($options)
    {
        // Assumption is that lowest common denominator for renderer configuration
        // is an array
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (! is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an array, or Traversable argument; received "%s"',
                __METHOD__,
                get_debug_type($options),
            ));
        }

        $this->options = $options;
        return $this;
    }

    /**
     * Get renderer options/hints
     *
     * @return array<string, mixed>
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Clear any existing renderer options/hints
     *
     * @return $this
     */
    public function clearOptions()
    {
        $this->options = [];
        return $this;
    }

    /**
     * Get a single view variable
     *
     * @param  string       $name
     * @param  mixed|null   $default (optional) default value if the variable is not present.
     * @return mixed
     */
    public function getVariable($name, $default = null)
    {
        $name = (string) $name;

        if (is_array($this->variables)) {
            if (array_key_exists($name, $this->variables)) {
                return $this->variables[$name];
            }
        } elseif ($this->variables->offsetExists($name)) {
            return $this->variables->offsetGet($name);
        }

        return $default;
    }

    /**
     * Set view variable
     *
     * @param  string $name
     * @param  mixed $value
     * @return ViewModel
     */
    public function setVariable($name, $value)
    {
        $this->variables[(string) $name] = $value;
        return $this;
    }

    /**
     * Set view variables en masse
     *
     * Can be an array or a Traversable + ArrayAccess object.
     *
     * @param  array|ArrayAccess|Traversable $variables
     * @param  bool $overwrite Whether or not to overwrite the internal container with $variables
     * @throws Exception\InvalidArgumentException
     * @return ViewModel
     */
    public function setVariables($variables, $overwrite = false)
    {
        if (! is_array($variables) && ! $variables instanceof Traversable) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s: expects an array, or Traversable argument; received "%s"',
                __METHOD__,
                is_object($variables) ? $variables::class : gettype($variables)
            ));
        }

        if ($overwrite) {
            if (is_object($variables) && ! $variables instanceof ArrayAccess) {
                $variables = ArrayUtils::iteratorToArray($variables);
            }

            $this->variables = $variables;
            return $this;
        }

        foreach ($variables as $key => $value) {
            $this->setVariable($key, $value);
        }

        return $this;
    }

    /**
     * Get view variables
     *
     * @return array|ArrayAccess|Traversable
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Clear all variables
     *
     * Resets the internal variable container to an empty container.
     *
     * @return ViewModel
     */
    public function clearVariables()
    {
        $this->variables = new ViewVariables();
        return $this;
    }

    /**
     * Set the template to be used by this model
     *
     * @param  string $template
     * @return ViewModel
     */
    public function setTemplate($template)
    {
        $this->template = (string) $template;
        return $this;
    }

    /**
     * Get the template to be used by this model
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Add a child model
     *
     * @param  null|string $captureTo Optional; if specified, the "capture to" value to set on the child
     * @param  null|bool $append Optional; if specified, append to child  with the same capture
     * @return ViewModel
     */
    public function addChild(ModelInterface $child, $captureTo = null, $append = null)
    {
        $this->children[] = $child;
        if (null !== $captureTo) {
            $child->setCaptureTo($captureTo);
        }
        if (null !== $append) {
            $child->setAppend($append);
        }

        return $this;
    }

    /**
     * Return all children.
     *
     * Return specifies an array, but may be any iterable object.
     *
     * @return list<ModelInterface>
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Does the model have any children?
     *
     * @return bool
     */
    public function hasChildren()
    {
        return (bool) $this->children;
    }

    /**
     * Clears out all child models
     *
     * @return ViewModel
     */
    public function clearChildren()
    {
        $this->children = [];
        return $this;
    }

    /**
     * Returns an array of Viewmodels with captureTo value $capture
     *
     * @param string $capture
     * @param bool $recursive search recursive through children, default true
     * @return list<ModelInterface>
     */
    public function getChildrenByCaptureTo($capture, $recursive = true)
    {
        $children = [];

        foreach ($this->children as $child) {
            if ($recursive === true && $child instanceof RetrievableChildrenInterface) {
                $children = array_merge($children, $child->getChildrenByCaptureTo($capture));
            }

            if ($child->captureTo() === $capture) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Set the name of the variable to capture this model to, if it is a child model
     *
     * @param  string $capture
     * @return ViewModel
     */
    public function setCaptureTo($capture)
    {
        $this->captureTo = (string) $capture;
        return $this;
    }

    /**
     * Get the name of the variable to which to capture this model
     *
     * @return string
     */
    public function captureTo()
    {
        return $this->captureTo;
    }

    /**
     * Set flag indicating whether or not this is considered a terminal or standalone model
     *
     * @param  bool $terminate
     * @return ViewModel
     */
    public function setTerminal($terminate)
    {
        $this->terminate = (bool) $terminate;
        return $this;
    }

    /**
     * Is this considered a terminal or standalone model?
     *
     * @return bool
     */
    public function terminate()
    {
        return $this->terminate;
    }

    /**
     * Set flag indicating whether or not append to child  with the same capture
     *
     * @param  bool $append
     * @return ViewModel
     */
    public function setAppend($append)
    {
        $this->append = (bool) $append;
        return $this;
    }

    /**
     * Is this append to child  with the same capture?
     *
     * @return bool
     */
    public function isAppend()
    {
        return $this->append;
    }

    /**
     * Return count of children
     *
     * @return int
     */
    #[ReturnTypeWillChange]
    public function count()
    {
        return count($this->children);
    }

    /**
     * Get iterator of children
     *
     * @return Traversable<int, ModelInterface>
     */
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->children);
    }
}
