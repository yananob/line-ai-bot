<?php

declare(strict_types=1);

use MyApp\PersonalConsultant;

final class PersonalConsultantTest extends PHPUnit\Framework\TestCase
{
    private PersonalConsultant $consultant;

    protected function setUp(): void
    {
        $this->consultant = new PersonalConsultant(__DIR__ . "/configs/config.json", "TEST_TARGET_ID");
    }

    public function testGetLineTarget()
    {
        $this->assertSame("TEST_LINE_TARGET", $this->consultant->getLineTarget());
    }
}
