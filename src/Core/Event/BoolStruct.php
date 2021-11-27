<?php declare(strict_types=1);

namespace EventCandy\Sets\Core\Event;

use Shopware\Core\Framework\Struct\Struct;

class BoolStruct extends Struct {

    /**
     * @var boolean
     */
    protected $value;

    /**
     * @param bool $value
     */
    public function __construct(bool $value)
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function getValue(): bool
    {
        return $this->value;
    }

    /**
     * @param bool $value
     */
    public function setValue(bool $value): void
    {
        $this->value = $value;
    }
}