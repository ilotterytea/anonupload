<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';

class SplitFilename
{
    public string $name, $extension;

    public function __construct(string $filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $l = strlen($ext);
        if ($l === 0) {
            throw new RuntimeException("No extension found");
        }

        $name = basename($filename);
        $this->name = substr($name, 0, strlen($name) - $l - 1);
        $this->extension = $ext;
    }
}

interface Identifier
{
    public function generate(int $length): string;
}

class RandomCharacterIdentifier implements Identifier
{
    private array $pool;
    private int $count;

    public function __construct(string $pool)
    {
        $this->pool = str_split($pool);
        $this->count = count($this->pool);
    }

    public function generate(int $length = CONFIG['id']['length']): string
    {
        $o = "";

        for ($i = 0; $i < $length; $i++) {
            $o .= $this->pool[random_int(0, $this->count - 1)];
        }

        return $o;
    }
}

define("IDENTIFIER", match (CONFIG['id']['type']) {
    "chars" => new RandomCharacterIdentifier(CONFIG['id']['pool']),
    default => throw new RuntimeException("Unsupported identifier type: " . CONFIG['id']['type'])
});