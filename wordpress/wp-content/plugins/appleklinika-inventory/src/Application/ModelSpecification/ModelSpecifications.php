<?php
declare(strict_types=1);
namespace Appleklinika\Inventory\Application\ModelSpecification;

interface ModelSpecifications
{
    /** Model-level data; empty rows and a warning when unknown. No product writes or network requests.
     * @return array{model_key:string,rows:array,source_url:string,source_title:string,fetched_at:string,status:string,warnings:list<string>}
     */
    public function resolve(string $modelKey): array;
}
