<?php

namespace IlBronza\MediaManager\Tests\Feature;

use IlBronza\MediaManager\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_cli_import_and_reconcile_default_to_preview(): void
    {
        $this->fixtureFile('a');
        $this->artisan('media-manager:import',['disk'=>'archive'])->assertSuccessful();
        $entry = $this->catalog->at('archive','a'); rename($this->root.'/a',$this->root.'/b');
        $this->artisan('media-manager:reconcile',['disk'=>'archive'])->assertSuccessful();
        $this->assertSame('a',$entry->fresh()->path);
        $this->artisan('media-manager:reconcile',['disk'=>'archive','--apply'=>true])->assertSuccessful();
        $this->assertSame('b',$entry->fresh()->path);
    }

    public function test_cli_missing_file_returns_a_failure_without_deleting_the_record(): void
    {
        $this->fixtureFile('a'); $entry = $this->catalog->import('archive','a'); unlink($this->root.'/a');
        $this->artisan('media-manager:reconcile',['disk'=>'archive','--apply'=>true])->assertFailed();
        $this->assertSame('missing',$entry->fresh()->status);
    }
}
