<?php declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * トリガースケジュールの形式または内容が不正な場合にスローされるドメイン固有の例外。
 */
class InvalidTriggerScheduleException extends DomainException
{
}
