<?php

declare(strict_types=1);

namespace AUS\SsiInclude\Tests;

use AUS\SsiInclude\Register\LastRenderedContentRegister;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class LastRenderedContentRegisterTest extends UnitTestCase
{
    #[Test]
    public function anUnrenderedFragmentReturnsEmptyContent(): void
    {
        $register = new LastRenderedContentRegister();

        self::assertSame('', $register->get('menu'));
    }

    #[Test]
    public function differentFragmentsKeepTheirOwnContent(): void
    {
        $register = new LastRenderedContentRegister();
        $register->set('menu', '<nav>Menu</nav>');
        $register->set('footer', '<footer>Footer</footer>');

        self::assertSame('<nav>Menu</nav>', $register->get('menu'));
        self::assertSame('<footer>Footer</footer>', $register->get('footer'));
    }

    #[Test]
    public function renderingAgainReplacesThePreviousFragmentContent(): void
    {
        $register = new LastRenderedContentRegister();
        $register->set('menu', '<nav>Old menu</nav>');
        $register->set('menu', '<nav>Updated menu</nav>');

        self::assertSame('<nav>Updated menu</nav>', $register->get('menu'));
    }
}
