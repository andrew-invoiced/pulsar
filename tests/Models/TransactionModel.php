<?php

namespace Pulsar\Tests\Models;

use Pulsar\Model;
use Pulsar\ModelEvent;

class TransactionModel extends Model
{
    protected static $properties = [
        'name' => [
            'required' => true,
            'validate' => 'string:5',
        ],
    ];

    protected function initialize()
    {
        parent::initialize();

        self::deleting(function (ModelEvent $modelEvent) {
            if ('delete fail' == $modelEvent->getModel()->name) {
                $modelEvent->stopPropagation();
            }
        });
    }

    protected function usesTransactions(): bool
    {
        return true;
    }
}