<?php

declare(strict_types=1);

namespace Laminas\View;

use ArrayAccess;
use Laminas\EventManager\Event;
use Laminas\Stdlib\RequestInterface as Request;
use Laminas\Stdlib\ResponseInterface as Response;
use Laminas\View\Model\ModelInterface as Model;
use Laminas\View\Renderer\RendererInterface as Renderer;

use function is_array;

/**
 * @psalm-type EventParams = array{
 *   model: Model|null,
 *   renderer: Renderer|null,
 *   request: Request|null,
 *   response: Response|null,
 *   result: mixed,
 *   ...
 * }
 * @template TTarget of null|object|string
 * @extends Event<TTarget, EventParams>
 * @final
 */
class ViewEvent extends Event
{
    /**#@+
     * View events triggered by eventmanager
     */
    public const EVENT_RENDERER      = 'renderer';
    public const EVENT_RENDERER_POST = 'renderer.post';
    public const EVENT_RESPONSE      = 'response';
    /**#@-*/

    /** @var null|Model */
    protected $model;

    /** @var Renderer|null */
    protected $renderer;

    /** @var null|Request */
    protected $request;

    /** @var null|Response */
    protected $response;

    /** @var mixed */
    protected $result;

    /**
     * Set the view model
     *
     * @return ViewEvent
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the MVC request object
     *
     * @return ViewEvent
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set the MVC response object
     *
     * @return ViewEvent
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Set result of rendering
     *
     * @param  mixed $result
     * @return ViewEvent
     */
    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * Retrieve the view model
     *
     * @return null|Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set value for renderer
     *
     * @return ViewEvent
     */
    public function setRenderer(Renderer $renderer)
    {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Get value for renderer
     *
     * @return null|Renderer
     */
    public function getRenderer()
    {
        return $this->renderer;
    }

    /**
     * Retrieve the MVC request object
     *
     * @return null|Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Retrieve the MVC response object
     *
     * @return null|Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Retrieve the result of rendering
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Get event parameter
     *
     * @param  string $name
     * @param  mixed $default
     * @return mixed
     */
    public function getParam($name, $default = null)
    {
        switch ($name) {
            case 'model':
                return $this->getModel();
            case 'renderer':
                return $this->getRenderer();
            case 'request':
                return $this->getRequest();
            case 'response':
                return $this->getResponse();
            case 'result':
                return $this->getResult();
            default:
                return parent::getParam($name, $default);
        }
    }

    /** @inheritDoc */
    public function getParams()
    {
        $params             = parent::getParams();
        $params['model']    = $this->getModel();
        $params['renderer'] = $this->getRenderer();
        $params['request']  = $this->getRequest();
        $params['response'] = $this->getResponse();
        $params['result']   = $this->getResult();
        return $params;
    }

    /**
     * Set event parameters
     *
     * @param  array|object|ArrayAccess $params
     * @return ViewEvent
     */
    public function setParams($params)
    {
        parent::setParams($params);
        if (! is_array($params) && ! $params instanceof ArrayAccess) {
            return $this;
        }

        foreach (['model', 'renderer', 'request', 'response', 'result'] as $param) {
            if (isset($params[$param])) {
                $method = 'set' . $param;
                $this->$method($params[$param]);
            }
        }
        return $this;
    }

    /**
     * Set an individual event parameter
     *
     * @param  string $name
     * @param  mixed $value
     * @return ViewEvent
     */
    public function setParam($name, $value)
    {
        switch ($name) {
            case 'model':
                $this->setModel($value);
                break;
            case 'renderer':
                $this->setRenderer($value);
                break;
            case 'request':
                $this->setRequest($value);
                break;
            case 'response':
                $this->setResponse($value);
                break;
            case 'result':
                $this->setResult($value);
                break;
            default:
                parent::setParam($name, $value);
                break;
        }
        return $this;
    }
}
