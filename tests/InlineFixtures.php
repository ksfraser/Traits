<?php

namespace Ksfraser\Traits\Tests;

class FakeInlineDao
{
    public $deleted = [];

    public function delete($stockId): void
    {
        $this->deleted[] = $stockId;
    }
}

class FakeUpsertAction
{
    public $calls = [];

    /** @var FakeInlineDao */
    public $dao;

    public function __construct(FakeInlineDao $dao)
    {
        $this->dao = $dao;
    }

    public function handle(string $stockId, array $postData): void
    {
        $this->calls[] = [$stockId, $postData];
    }
}

class FakeTabRenderer
{
    public $rendered = [];

    /** @var object|null */
    public $dao;

    public function __construct($dao)
    {
        $this->dao = $dao;
    }

    public function render(string $stockId): void
    {
        $this->rendered[] = $stockId;
    }
}

class FakeExtraTabRenderer
{
    public $rendered = [];

    /** @var object|null */
    public $dao;

    /** @var string|null */
    public $extra;

    public function __construct($dao, string $extra)
    {
        $this->dao   = $dao;
        $this->extra = $extra;
    }

    public function render(string $stockId): void
    {
        $this->rendered[] = $stockId;
    }
}
