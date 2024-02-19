<?php

namespace App\Exception;

class MyCurrentException extends \Exception
{
    private string $codeWork;

    /**
     * @param string $codeWork
     * @param string $message
     * @param int|null $code
     */
    public function __construct(string $codeWork, string $message, int $code = null)
    {
        $this->codeWork = $codeWork;
        parent::__construct($message, $code);

    }

    public function getCodeWork(): string
    {
        return $this->codeWork;
    }
}
