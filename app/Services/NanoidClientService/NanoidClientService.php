<?php

namespace App\Services\NanoidClientService;
use Hidehalo\Nanoid\Client as NanoidClient;
class NanoidClientService
{
    protected $alphabet;
    protected $size;

    public function __construct($size = 21)
    {
        $this->size = $size > 0 ? $size : 21;
        $this->alphabet = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    public function generate(
        int $size = 0
    ) {
        $nanoid = new NanoidClient();
        $size = $size > 0 ? $size : $this->size;
        return $nanoid->generateId($size);
    }

    public function formattedId(
        int $size = 0,
    ) {
        $nanoid = new NanoidClient();
        return $nanoid->formattedId($this->alphabet, $size);
    }


}