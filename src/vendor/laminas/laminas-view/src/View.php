<?php

declare(strict_types=1);

namespace Laminas\View;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface as Model;
use Laminas\View\Renderer\RendererInterface as Renderer;
use Laminas\View\Renderer\TreeRendererInterface;

use function array_key_exists;
use function sprintf;

/** @final */
class View implements EventManagerAwareInterface
{
    /** @var EventManagerInterface */
    protected $events;

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /**
     * Set MVC request object
     *
     * @return View
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set MVC response object
     *
     * @return View
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get MVC request object
     *
     * @return null|Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get MVC response object
     *
     * @return null|Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set the event manager instance
     *
     * @return View
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers([
            self::class,
            static::class,
        ]);
        $this->events = $events;
        return $this;
    }

    /**
     * Retrieve the event manager instance
     *
     * Lazy-loads a default instance if none available
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (! $this->events instanceof EventManagerInterface) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Add a rendering strategy
     *
     * Expects a callable. Strategies should accept a ViewEvent object, and should
     * return a Renderer instance if the strategy is selected.
     *
     * Internally, the callable provided will be subscribed to the "renderer"
     * event, at the priority specified.
     *
     * @param  callable $callable
     * @param  int $priority
     * @return View
     */
    public function addRenderingStrategy($callable, $priority = 1)
    {
        $this->getEventManager()->attach(ViewEvent::EVENT_RENDERER, $callable, $priority);
        return $this;
    }

    /**
     * Add a response strategy
     *
     * Expects a callable. Strategies should accept a ViewEvent object. The return
     * value will be ignored.
     *
     * Typical usages for a response strategy are to populate the Response object.
     *
     * Internally, the callable provided will be subscribed to the "response"
     * event, at the priority specified.
     *
     * @param  callable $callable
     * @param  int $priority
     * @return View
     */
    public function addResponseStrategy($callable, $priority = 1)
    {
        $this->getEventManager()->attach(ViewEvent::EVENT_RESPONSE, $callable, $priority);
        return $this;
    }

    /**
     * Render the provided model.
     *
     * Internally, the following workflow is used:
     *
     * - Trigger the "renderer" event to select a renderer.
     * - Call the selected renderer with the provided Model
     * - Trigger the "response" event
     *
     * @triggers renderer(ViewEvent)
     * @triggers response(ViewEvent)
     * @throws Exception\RuntimeException
     * @return null|string
     */
    public function render(Model $model)
    {
        $event = $this->getEvent();
        $event->setModel($model);
        $event->setName(ViewEvent::EVENT_RENDERER);
        $events   = $this->getEventManager();
        $results  = $events->triggerEventUntil(static fn($result) => $result instanceof Renderer, $event);
        $renderer = $results->last();
        if (! $renderer instanceof Renderer) {
            throw new Exception\RuntimeException(sprintf(
                '%s: no renderer selected!',
                __METHOD__
            ));
        }

        $event->setRenderer($renderer);
        $event->setName(ViewEvent::EVENT_RENDERER_POST);
        $events->triggerEvent($event);

        // If EVENT_RENDERER or EVENT_RENDERER_POST changed the model, make sure
        // we use this new model instead of the current $model
        $model = $event->getModel();

        // If we have children, render them first, but only if:
        // a) the renderer does not implement TreeRendererInterface, or
        // b) it does, but canRenderTrees() returns false
        if (
            $model->hasChildren()
            && (! $renderer instanceof TreeRendererInterface
                || ! $renderer->canRenderTrees())
        ) {
            $this->renderChildren($model);
        }

        // Reset the model, in case it has changed, and set the renderer
        $event->setModel($model);
        $event->setRenderer($renderer);

        $rendered = $renderer->render($model);

        // If this is a child model, return the rendered content; do not
        // invoke the response strategy.
        $options = $model->getOptions();
        if (array_key_exists('has_parent', $options) && $options['has_parent']) {
            return $rendered;
        }

        $event->setResult($rendered);
        $event->setName(ViewEvent::EVENT_RESPONSE);

        $events->triggerEvent($event);
    }

    /**
     * Loop through children, rendering each
     *
     * @throws Exception\DomainException
     * @return void
     */
    protected function renderChildren(Model $model)
    {
        foreach ($model as $child) {
            if ($child->terminate()) {
                throw new Exception\DomainException('Inconsistent state; child view model is marked as terminal');
            }
            $child->setOption('has_parent', true);
            $result = $this->render($child);
            $child->setOption('has_parent', null);
            $capture = $child->captureTo();
            if (! empty($capture)) {
                if ($child->isAppend()) {
                    $oldResult = $model->{$capture};
                    $model->setVariable($capture, $oldResult . $result);
                } else {
                    $model->setVariable($capture, $result);
                }
            }
        }
    }

    /**
     * Create and return ViewEvent used by render()
     *
     * @return ViewEvent
     */
    protected function getEvent()
    {
        $event = new ViewEvent();
        $event->setTarget($this);
        if (null !== ($request = $this->getRequest())) {
            $event->setRequest($request);
        }
        if (null !== ($response = $this->getResponse())) {
            $event->setResponse($response);
        }
        return $event;
    }
}
