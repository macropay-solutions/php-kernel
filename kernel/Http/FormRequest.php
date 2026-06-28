<?php

namespace MacropaySolutions\Kernel\Http;

use MacropaySolutions\Kernel\Auth\Access\AuthorizationException;
use MacropaySolutions\Kernel\Auth\Access\Response;
use MacropaySolutions\Kernel\Contracts\Container\Container;
use MacropaySolutions\Kernel\Contracts\Validation\Factory as ValidationFactory;
use MacropaySolutions\Kernel\Contracts\Validation\ValidatesWhenResolved;
use MacropaySolutions\Kernel\Contracts\Validation\Validator;
use MacropaySolutions\Kernel\Validation\ValidatesWhenResolvedTrait;
use MacropaySolutions\Kernel\Validation\ValidationException;

abstract class FormRequest extends Request implements ValidatesWhenResolved
{
    use ValidatesWhenResolvedTrait;

    /**
     * The container instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Container\Container
     */
    protected $container;

    /**
     * The URI to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirect;

    /**
     * The route to redirect to if validation fails.
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     * The key to be used for the view error bag.
     *
     * @var string
     */
    protected $errorBag = 'default';

    /**
     * Indicates whether validation should stop after the first rule failure.
     *
     * @var bool
     */
    protected $stopOnFirstFailure = false;

    /**
     * The validator instance.
     *
     * @var \MacropaySolutions\Kernel\Contracts\Validation\Validator
     */
    protected $validator;

    /**
     * Get the validator instance for the request.
     *
     * @return \MacropaySolutions\Kernel\Contracts\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        if ($this->validator) {
            return $this->validator;
        }

        $factory = $this->container->make(ValidationFactory::class);

        if (method_exists($this, 'validator')) {
            $validator = $this->container->call([$this, 'validator'], compact('factory'));
        } else {
            $validator = $this->createDefaultValidator($factory);
        }

        if (method_exists($this, 'withValidator')) {
            $this->withValidator($validator);
        }

        if (method_exists($this, 'after')) {
            $validator->after(
                $this->container->call(
                    $this->after(...),
                    ['validator' => $validator]
                )
            );
        }

        $this->setValidator($validator);

        return $this->validator;
    }

    /**
     * Create the default validator instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Validation\Factory $factory
     * @return \MacropaySolutions\Kernel\Contracts\Validation\Validator
     */
    protected function createDefaultValidator(ValidationFactory $factory)
    {
        $rules = $this->validationRules();

        $validator = $factory->make(
            $this->validationData(),
            $rules,
            $this->messages(),
            $this->attributes(),
        )->stopOnFirstFailure($this->stopOnFirstFailure);

        if ($this->isPrecognitive()) {
            $validator->setRules(
                $this->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders())
            );
        }

        return $validator;
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData()
    {
        return $this->all();
    }

    /**
     * Get the validation rules for this form request.
     *
     * @return array
     */
    protected function validationRules()
    {
        return method_exists($this, 'rules') ? $this->container->call([$this, 'rules']) : [];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        $exception = $validator->getException();
        /** @var ValidationException  $ex */
        $ex = new $exception($validator);

        throw $ex->errorBag($this->errorBag)->redirectTo($this->getRedirectUrl());
    }

    /**
     * Get the URL to redirect to on a validation error.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $redirector = new \MacropaySolutions\Framework\Http\Redirector($this->container);

        if ($this->redirect) {
            return $redirector->to($this->redirect);
        }

        if ($this->redirectRoute) {
            return $redirector->route($this->redirectRoute);
        }

        return $redirector->to('/');
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     *
     * @throws \MacropaySolutions\Kernel\Auth\Access\AuthorizationException
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize')) {
            $result = $this->container->call([$this, 'authorize']);

            return $result instanceof Response ? $result->authorize() : $result;
        }

        return true;
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \MacropaySolutions\Kernel\Auth\Access\AuthorizationException
     */
    protected function failedAuthorization()
    {
        throw new AuthorizationException();
    }

    /**
     * Get a validated input container for the validated input.
     *
     * @param array|null $keys
     * @return \MacropaySolutions\Kernel\Support\ValidatedInput|array
     */
    public function safe(?array $keys = null)
    {
        return is_array($keys)
            ? $this->validator->safe()->only($keys)
            : $this->validator->safe();
    }

    /**
     * Get the validated data from the request.
     *
     * @param array|int|string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        return data_get($this->validator->validated(), $key, $default);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }

    /**
     * Set the Validator instance.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Validation\Validator $validator
     * @return $this
     */
    public function setValidator(Validator $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Set the container implementation.
     *
     * @param \MacropaySolutions\Kernel\Contracts\Container\Container $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }
}
