<?php

namespace LaravelSimpleModule\Traits;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * @trait ValidateChoice
 * https://github.com/smashed-egg/laravel-console/blob/master/docs/traits.md
 */
trait ValidateChoice
{

    /**
     * Prompt the user for input until validation passes.
     *
     * @param string $question
     * @param string|null $default
     * @param array $rules
     * @param array $messages
     * @return mixed
     */
    public function askAndValidate($question, $default = null, array $rules = [], array $messages = []): mixed
    {
        $value = $this->ask($question, $default);

        while ( ! $this->validateInput($value, $rules, $messages)) {
            $value = $this->ask($question, $default);
        }

        return $value;
    }

    /**
     * Prompt the user for input with completion until validation passes.
     *
     * @param string $question
     * @param array|callable $choices
     * @param null $default
     * @param array $rules
     * @param array $messages
     * @return mixed
     */
    public function askWithCompletionAndValidate($question, $choices, $default = null, array $rules = [], array $messages = []): mixed
    {
        $value = $this->askWithCompletion($question, $choices, $default);

        while ( ! $this->validateInput($value, $rules, $messages)) {
            $value = $this->askWithCompletion($question, $choices, $default);
        }

        return $value;
    }

    /**
     * Validate input based on given rules. Any errors will be output to the user.
     *
     * @param $value
     * @param array $rules
     * @param array $messages
     * @return bool
     */
    public function validateInput($value, array $rules, array $messages = []): bool
    {
        try {
            Validator::make(['input' => $value], ['input' => $rules], $messages)->validate();

            return true;
        } catch (ValidationException $e) {
            $this->error('Invalid input:');
            foreach (Arr::get($e->errors(), 'input', []) as $error) {
                $this->error($error);
            }
        }

        return false;
    }
}
