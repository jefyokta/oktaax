<?php

namespace Oktaax\Core\Promise;

enum PromiseState
{
    case Pending;
    case Fulfilled;
    case Rejected;
};
