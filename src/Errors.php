<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace Pulsar;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Infuse\Locale;
use IteratorAggregate;

/**
 * Holds error messages generated by models, like validation errors.
 */
class Errors implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var array
     */
    private static $messages = [
        'pulsar.validation.alpha' => '{{field_name}} only allows letters',
        'pulsar.validation.alpha_numeric' => '{{field_name}} only allows letters and numbers',
        'pulsar.validation.alpha_dash' => '{{field_name}} only allows letters and dashes',
        'pulsar.validation.boolean' => '{{field_name}} must be yes or no',
        'pulsar.validation.custom' => '{{field_name}} validation failed',
        'pulsar.validation.email' => '{{field_name}} must be a valid email address',
        'pulsar.validation.enum' => '{{field_name}} must be one of the allowed values',
        'pulsar.validation.date' => '{{field_name}} must be a date',
        'pulsar.validation.failed' => '{{field_name}} is invalid',
        'pulsar.validation.ip' => '{{field_name}} only allows valid IP addresses',
        'pulsar.validation.matching' => '{{field_name}} must match',
        'pulsar.validation.numeric' => '{{field_name}} only allows numbers',
        'pulsar.validation.password' => '{{field_name}} must meet the password requirements',
        'pulsar.validation.range' => '{{field_name}} must be within the allowed range',
        'pulsar.validation.required' => '{{field_name}} is missing',
        'pulsar.validation.string' => '{{field_name}} must be a string of the proper length',
        'pulsar.validation.time_zone' => '{{field_name}} only allows valid time zones',
        'pulsar.validation.timestamp' => '{{field_name}} only allows timestamps',
        'pulsar.validation.unique' => 'The {{field_name}} you chose has already been taken. Please try a different {{field_name}}.',
        'pulsar.validation.url' => '{{field_name}} only allows valid URLs',
    ];

    /**
     * @var array
     */
    private $stack = [];

    /**
     * @var Locale|null
     */
    private $locale;

    /**
     * Sets the locale service.
     *
     * @param Locale $locale
     *
     * @return self
     */
    public function setLocale(Locale $locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Gets the locale service.
     *
     * @return Locale
     */
    public function getLocale()
    {
        if (!$this->locale) {
            $this->locale = new Locale();
        }

        return $this->locale;
    }

    /**
     * Adds an error message to the stack.
     *
     * @param $error
     * @param array $parameters
     *
     * @return $this
     */
    public function add($error, array $parameters = [])
    {
        $this->stack[] = [
            'error' => $error,
            'params' => $parameters,
        ];

        return $this;
    }

    /**
     * @deprecated
     *
     * Adds an error message to the stack
     *
     * @param array|string $error
     *                            - error: error code
     *                            - params: array of parameters to be passed to message
     *
     * @return self
     */
    public function push($error)
    {
        $this->stack[] = $this->sanitize($error);

        return $this;
    }

    /**
     * Gets all of the errors on the stack and also attempts
     * translation using the Locale class.
     *
     * @param string $locale optional locale
     *
     * @return array error messages
     */
    public function all($locale = '')
    {
        $messages = [];
        foreach ($this->stack as $error) {
            $messages[] = $this->parse($error['error'], $locale, $error['params']);
        }

        return $messages;
    }

    /**
     * @deprecated
     *
     * Gets all of the errors on the stack and also attempts
     * translation using the Locale class
     *
     * @param string $locale optional locale
     *
     * @return array errors
     */
    public function errors($locale = '')
    {
        $errors = [];
        foreach ($this->stack as $error) {
            if (!isset($error['message'])) {
                $error['message'] = $this->parse($error['error'], $locale, $error['params']);
            }

            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @deprecated
     *
     * Gets the messages of errors on the stack
     *
     * @param string $locale optional locale
     *
     * @return array errors
     */
    public function messages($locale = '')
    {
        return $this->all($locale);
    }

    /**
     * Gets an error for a specific parameter on the stack.
     *
     * @param string $value value we are searching for
     * @param string $param parameter name
     *
     * @return array|bool
     */
    public function find($value, $param = 'field')
    {
        foreach ($this->errors() as $error) {
            if (array_value($error['params'], $param) === $value) {
                return $error;
            }
        }

        return false;
    }

    /**
     * Checks if an error exists with a specific parameter on the stack.
     *
     * @param string $value value we are searching for
     * @param string $param parameter name
     *
     * @return bool
     */
    public function has($value, $param = 'field')
    {
        return $this->find($value, $param) !== false;
    }

    /**
     * Clears the error stack.
     *
     * @return self
     */
    public function clear()
    {
        $this->stack = [];

        return $this;
    }

    /**
     * Formats an incoming error message.
     *
     * @param array|string $error
     *
     * @return array
     */
    private function sanitize($error)
    {
        if (!is_array($error)) {
            $error = ['error' => $error];
        }

        if (!isset($error['params'])) {
            $error['params'] = [];
        }

        return $error;
    }

    /**
     * Parses an error message before displaying it.
     *
     * @param string       $error
     * @param string|false $locale
     * @param array        $parameters
     *
     * @return string
     */
    private function parse($error, $locale, array $parameters)
    {
        // try to supply a fallback message in case
        // the user does not have one specified
        $fallback = array_value(self::$messages, $error);

        return $this->getLocale()->t($error, $parameters, $locale, $fallback);
    }

    //////////////////////////
    // IteratorAggregate Interface
    //////////////////////////

    public function getIterator()
    {
        return new ArrayIterator($this->stack);
    }

    //////////////////////////
    // Countable Interface
    //////////////////////////

    /**
     * Get total number of models matching query.
     *
     * @return int
     */
    public function count()
    {
        return count($this->stack);
    }

    /////////////////////////////
    // ArrayAccess Interface
    /////////////////////////////

    public function offsetExists($offset)
    {
        return isset($this->stack[$offset]);
    }

    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \OutOfBoundsException("$offset does not exist on this ErrorStack");
        }

        return $this->errors()[$offset];
    }

    public function offsetSet($offset, $error)
    {
        if (!is_numeric($offset)) {
            throw new \Exception('Can only perform set on numeric indices');
        }

        $this->stack[$offset] = $this->sanitize($error);
    }

    public function offsetUnset($offset)
    {
        unset($this->stack[$offset]);
    }
}