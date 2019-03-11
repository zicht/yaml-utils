<?php
/**
 * @author    Philip Bergman <philip@zicht.nl>
 * @copyright Zicht Online <http://www.zicht.nl>
 */
declare(strict_types=1);

namespace App\Transport;

class Context
{
    const STATE_OK = 0x01;
    const STATE_ERROR = 0x02;

    /** @var array */
    private $context = [];
    /** @var int */
    private $state;

    /**
     * @param array $context
     */
    public function __construct(array  $context = [])
    {
        $this->context = $context;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param int $state
     * @return Context
     */
    public function setState(int $state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @param null $key
     * @param null $default
     * @return mixed
     */
    public function getContext($key = null, $default = null)
    {
        if (null === $key) {
            return $this->context;
        }

        return isset($this->context[$key]) ? $this->context[$key] : $default;
    }

    /**
     * @param array $context
     */
    public function setContext(array $context)
    {
        $this->context = $context;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @return Context
     */
    public function addContext($key, $value)
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * @param resource $resource
     * @return int
     * @throws \RuntimeException
     */
    public function write($resource) :int
    {
        if (false === $data = serialize($this->context)) {
            throw new \RuntimeException('failed ot serialize context');
        }

        $size = strlen($data);
        $chrs = [];

        for ($i = 0; $i < $size; $i++) {
            $chrs[] = ord($data[$i]);
        }

        if (false === $s = fwrite($resource, pack('VCC*', $size, $this->state, ...$chrs), $size+5)) {
            throw new \RuntimeException('failed to write context');
        }

        fflush($resource);

        return $s;
    }

    /**
     * @param resource $resource
     * @throws \RuntimeException
     * @return Context
     */
    public function read($resource) :self
    {
        if (false === $raw = fread($resource, 5)) {
            throw new \RuntimeException(sprintf('failed to get size from %s', (string)$resource));
        }

        $data = unpack('Vsize/Cstate', $raw);

        if (false === $raw = fread($resource, $data['size'])) {
            throw new \RuntimeException(sprintf('failed to read (%d) bytes from %s', $data['size'], (string)$resource));
        }

        if (false === $this->context = unserialize(implode('', array_map('chr', unpack('C' . $data['size'], $raw))))) {
            throw new \RuntimeException('failed to unserialize context');
        }

        $this->state = $data['state'];

        return $this;
    }

    /**
     * @param resource$resource
     * @return Context
     */
    public static function newFromResource($resource) :self
    {
        return (new self)->read($resource);
    }
}
