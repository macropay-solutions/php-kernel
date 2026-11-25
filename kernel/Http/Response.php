<?php

namespace MacropaySolutions\Kernel\Http;

use ArrayObject;
use InvalidArgumentException;
use JsonSerializable;
use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use MacropaySolutions\Kernel\Contracts\Support\Jsonable;
use MacropaySolutions\Kernel\Contracts\Support\Renderable;
use MacropaySolutions\Kernel\Macroable\Contracts\Macroable;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Response extends SymfonyResponse implements Macroable
{
    use ResponseTrait;
    use \MacropaySolutions\Framework\Traitables\MacropaySolutionsKernelHttpResponse;

    /**
     * Create a new HTTP response.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($content = '', $status = 200, array $headers = [])
    {
        if (\is_string($content ?? '')) {
            parent::__construct($content, (int)$status, $headers);

            return;
        }

        parent::__construct('', (int)$status, $headers);
        $this->setContent($content);
    }

    /**
     * Set the content on the response.
     *
     * @param mixed $content
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setContent(mixed $content): static
    {
        $this->original = $content;

        if (\is_string($content ?? '')) {
            parent::setContent($content);

            return $this;
        }

        // If the content is "JSONable" we will set the appropriate header and convert
        // the content to JSON. This is useful when returning something like models
        // from routes that will be automatically transformed to their JSON form.
        if ($this->shouldBeJson($content)) {
            $this->header('Content-Type', 'application/json');

            $content = $this->morphToJson($content);

            if ($content === false) {
                throw new InvalidArgumentException(json_last_error_msg());
            }

            parent::setContent($content);

            return $this;
        }

        if ($content instanceof Renderable) {
            // If this content implements the "Renderable" interface then we will call the
            // render method on the object so we will avoid any "__toString" exceptions
            // that might be thrown and have their errors obscured by PHP's handling.
            $content = $content->render();
        }

        parent::setContent((string)$content);

        return $this;
    }

    /**
     * Determine if the given content should be turned into JSON.
     *
     * @param mixed $content
     * @return bool
     */
    protected function shouldBeJson($content)
    {
        return \is_array($content) ||
            $content instanceof Arrayable ||
            $content instanceof Jsonable ||
            $content instanceof ArrayObject ||
            $content instanceof JsonSerializable;
    }

    /**
     * Morph the given content into JSON.
     *
     * @param mixed $content
     * @return string|false
     */
    protected function morphToJson($content)
    {
        if ($content instanceof Jsonable) {
            return $content->toJson();
        }

        if ($content instanceof Arrayable) {
            return \json_encode($content->toArray());
        }

        return \json_encode($content);
    }
}
