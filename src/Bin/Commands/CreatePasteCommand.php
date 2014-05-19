<?php namespace Lio\Bin\Commands;

use Lio\Accounts\Member;

class CreatePasteCommand
{
    public $code;
    public $author;

    public function __construct($code, $author)
    {
        $this->code = $code;
        $this->author = $author;
    }
}