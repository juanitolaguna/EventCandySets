<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

use EventCandy\Sets\Test\Utils\ToDoTestBehaviour;
use PHPUnit\Framework\TestCase;

class PayloadServiceTest extends TestCase
{
    use ToDoTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testItCreatesUsablePayloadData() {
        $this->todo();
        //create Products with SubProducts
        // add Products to LineItem
        // add Dynamic Products to CartDataCollection with LineItemDataId
        // add create Payload
        // test the result not empty
        // test rows quantity
    }

    public function testItBuildsAPayloadObject() {
        $this->todo();
    }

    public function testItCalculatesTheCorrectWeight() {
        $this->todo();
    }


    public function testItCreatesPayloadAssociativeArray() {
        $this->todo();
    }

    public function testItCreatesAStringRepresentation() {
        $this->todo();
    }

}