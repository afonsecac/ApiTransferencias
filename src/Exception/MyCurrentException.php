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
        parent::__construct($message);
        $this->codeWork = $codeWork;
    }

    public function getCodeWork(): string
    {
        return $this->codeWork;
    }
}
