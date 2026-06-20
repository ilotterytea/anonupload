<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/config.php';

class SplitFilename
{
    public string $name;
    public string|null $extension = null;

    public function __construct(string $filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = basename($filename);
        $l = strlen($ext);

        if ($l > 0) {
            $name = substr($name, 0, strlen($name) - $l - 1);
        }

        $this->name = $name;
        $this->extension = $ext ?: null;
    }
}

interface Identifier
{
    public function generate(int $length): string;
}

class SnowflakeIdentifier implements Identifier
{
    private int $id, $epoch;

    public function __construct(int $machine_id, int $epoch = 1609459200000)
    {
        $this->id = $machine_id;
        $this->epoch = $epoch;
    }

    public function generate(int $length = 0): string
    {
        static $last_timestamp = 0;
        static $sequence = 0;

        $machine_id_bits = 10;
        $sequence_bits = 12;

        $max_machine_id = (1 << $machine_id_bits) - 1;
        $max_sequence = (1 << $sequence_bits) - 1;

        if ($this->id < 0 || $this->id > $max_machine_id) {
            throw new InvalidArgumentException("Machine ID must be between 0 and $max_machine_id");
        }

        $timestamp = (int) floor(microtime(true) * 1000);

        if ($timestamp < $last_timestamp) {
            throw new RuntimeException("Clock moved backwards. Refusing to generate ID.");
        }

        if ($timestamp === $last_timestamp) {
            $sequence = ($sequence + 1) & $max_sequence;

            if ($sequence === 0) {
                do {
                    $timestamp = (int) floor(microtime(true) * 1000);
                } while ($timestamp <= $last_timestamp);
            }
        } else {
            $sequence = 0;
        }

        $last_timestamp = $timestamp;

        return
            (($timestamp - $this->epoch) << ($machine_id_bits + $sequence_bits)) |
            ($this->id << $sequence_bits) |
            $sequence;
    }
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