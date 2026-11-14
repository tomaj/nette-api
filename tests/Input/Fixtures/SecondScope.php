<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\First {
    use Tomaj\NetteApi\Validation\Email as EmailAlias;

    const FIRST_SCOPE = EmailAlias::class;
}

namespace Tomaj\NetteApi\Test\Input\Fixtures {
    use Second\Imported\Widget;

    final class SecondScope
    {
        public const string NAME = Widget::class;
    }
}
