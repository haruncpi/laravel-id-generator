<?php namespace Haruncpi\LaravelIdGenerator\Tests\Unit;

use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdGenerateTest extends TestCase
{
    protected $testTable = 'invoices';

    public function test_table_name_required()
    {
        try {
            $config = ['prefix' => 'ID', 'length' => 10];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertEquals("Must need a table name", $e->getMessage());
        }
    }

    public function test_prefix_required()
    {
        try {
            $config = ['table' => 'users', 'length' => 10];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertEquals("Must specify a prefix of your ID", $e->getMessage());
        }
    }

    public function test_ID_length_required()
    {
        try {
            $config = ['table' => 'users', 'prefix' => 10];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertEquals("Must specify the length of ID", $e->getMessage());
        }
    }

    public function test_table_not_found()
    {
        try {
            $table = "blah";
            $config = ['table' => $table, 'prefix' => 'ID', 'length' => 10];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertEquals(0, $e->getCode());
        }

    }

    public function test_field_not_found()
    {
        try {
            $table = "users";
            $field = "emp_id";
            $config = ['table' => $table, 'prefix' => 'ID', 'length' => 10, 'field' => $field];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertEquals("$field not found in $table table", $e->getMessage());
        }
    }

    public function test_invalid_data_type()
    {
        try {
            $table = "users";
            $field = "id";
            $config = ['table' => $table, 'prefix' => 'ID', 'length' => 10];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertStringContainsString("$field field type is", $e->getMessage());
        }

    }

    public function test_invalid_length()
    {
        try {
            $table = "users";
            $config = ['table' => $table, 'prefix' => 101, 'length' => 25];
            IdGenerator::generate($config);
        } catch (\Exception $e) {
            $this->assertStringContainsString("Generated ID length is bigger then table field length", $e->getMessage());
        }

    }

    public function test_ID_generated()
    {

        $config = ['table' => $this->testTable, 'prefix' => 101, 'length' => 10];
        $id = IdGenerator::generate($config);
        $this->assertEquals($id, $id);

    }

    public function test_ID_incremented()
    {
        DB::beginTransaction();

        $config = ['table' => $this->testTable, 'prefix' => 101, 'length' => 10];
        $id1 = IdGenerator::generate($config);
        DB::table($this->testTable)->insert(['id' => $id1]);
        $id2 = IdGenerator::generate($config);
        DB::table($this->testTable)->insert(['id' => $id2]);

        DB::rollBack();
        $this->assertEquals($id1 + 1, $id2);

    }

    public function test_ID_reset_on_prefix_change()
    {
        DB::beginTransaction();
        $config_1 = ['table' => $this->testTable, 'prefix' => 101, 'length' => 10, 'reset_on_prefix_change' => true];
        $id1 = IdGenerator::generate($config_1);
        DB::table($this->testTable)->insert(['id' => $id1]);

        $id2 = IdGenerator::generate($config_1);
        DB::table($this->testTable)->insert(['id' => $id2]);

        $config_2 = ['table' => $this->testTable, 'prefix' => 102, 'length' => 10, 'reset_on_prefix_change' => true];
        $resetID = IdGenerator::generate($config_2);
        DB::table($this->testTable)->insert(['id' => $resetID]);

        DB::rollBack();
        $this->assertNotEquals($id2 + 1, $resetID);

    }
}
