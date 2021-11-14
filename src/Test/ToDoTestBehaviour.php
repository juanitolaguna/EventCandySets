<?php

declare(strict_types=1);

namespace EventCandy\Sets\Test;

trait ToDoTestBehaviour {
    /**
     * Marks a test as incomplete with a useful message
     */
    protected function todo(): void
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        self::markTestIncomplete(sprintf('Todo: %s::%s', $caller['class'], $caller['function']));
    }

}