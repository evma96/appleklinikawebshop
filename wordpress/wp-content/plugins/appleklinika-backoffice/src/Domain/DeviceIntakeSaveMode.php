<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Domain;

enum DeviceIntakeSaveMode:string
{
    case Draft='draft';
    case Publish='publish';
    case Next='next';

    public static function parse(mixed $value):self
    {
        if(!is_string($value)||!($mode=self::tryFrom($value)))throw new \InvalidArgumentException('Érvénytelen mentési mód. Válassz a mentési gombok közül.');
        return $mode;
    }
    public function status():string{return $this===self::Publish?'publish':'draft';}
}
