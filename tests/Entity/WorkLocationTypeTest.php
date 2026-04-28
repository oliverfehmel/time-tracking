<?php

namespace App\Tests\Entity;

use App\Entity\WorkLocationType;
use PHPUnit\Framework\TestCase;

final class WorkLocationTypeTest extends TestCase
{
    public function testDefaults(): void
    {
        $type = new WorkLocationType();

        $this->assertTrue($type->isActive());
        $this->assertFalse($type->isDefault());
        $this->assertNull($type->getIcon());
    }

    public function testSetAndGetName(): void
    {
        $type = new WorkLocationType();
        $type->setName('Home Office');

        $this->assertSame('Home Office', $type->getName());
    }

    public function testSetAndGetKeyName(): void
    {
        $type = new WorkLocationType();
        $type->setKeyName('home_office');

        $this->assertSame('home_office', $type->getKeyName());
    }

    public function testSetIsDefault(): void
    {
        $type = new WorkLocationType();
        $type->setIsDefault(true);

        $this->assertTrue($type->isDefault());
    }

    public function testSetIcon(): void
    {
        $type = new WorkLocationType();
        $type->setIcon('fa-solid fa-house');

        $this->assertSame('fa-solid fa-house', $type->getIcon());
    }

    public function testSetIconEmptyString_normalizesToNull(): void
    {
        $type = new WorkLocationType();
        $type->setIcon('fa-solid fa-house');
        $type->setIcon('');

        $this->assertNull($type->getIcon());
    }

    public function testSetIconNull(): void
    {
        $type = new WorkLocationType();
        $type->setIcon('fa-solid fa-house');
        $type->setIcon(null);

        $this->assertNull($type->getIcon());
    }
}
