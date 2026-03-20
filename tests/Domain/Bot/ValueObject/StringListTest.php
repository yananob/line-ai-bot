<?php declare(strict_types=1);

namespace Tests\Domain\Bot\ValueObject;

use PHPUnit\Framework\TestCase;
use App\Domain\Bot\ValueObject\StringList;

class StringListTest extends TestCase
{
    public function test_isEmpty_returns_true_for_empty_array(): void
    {
        $list = new StringList([]);
        $this->assertTrue($list->isEmpty());
    }

    public function test_isEmpty_returns_false_for_non_empty_array(): void
    {
        $list = new StringList(['a']);
        $this->assertFalse($list->isEmpty());
    }

    public function test_toArray_returns_original_values(): void
    {
        $values = ['a', 'b', 'c'];
        $list = new StringList($values);
        $this->assertSame($values, $list->toArray());
    }

    public function test_format_returns_empty_string_for_empty_list(): void
    {
        $list = new StringList([]);
        $this->assertSame("", $list->format());
    }

    public function test_format_returns_formatted_string_for_non_empty_list(): void
    {
        $list = new StringList(['item1', 'item2']);
        $this->assertSame("・item1\n・item2", $list->format());
    }

    public function test_merge_returns_new_instance_with_combined_values(): void
    {
        $list1 = new StringList(['a']);
        $list2 = new StringList(['b']);
        $merged = $list1->merge($list2);

        $this->assertNotSame($list1, $merged);
        $this->assertNotSame($list2, $merged);
        $this->assertSame(['a', 'b'], $merged->toArray());
    }
}
