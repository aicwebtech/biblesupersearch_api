<?php 
    use Illuminate\Database\Migrations\Migration; 
    use Illuminate\Database\Schema\Blueprint; 
    use Illuminate\Support\Facades\Schema; 
    
    return new class extends Migration { 
        public function up(): void 
        { 
            $this->renameUniqueIfLegacyNameExists( 'ip_access_log', 'ixcv', 'ux_ip_access_log_ip_id_date', ['ip_id', 'date'] ); 
            $this->renameUniqueIfLegacyNameExists( 'api_key_access_log', 'ixcv', 'ux_api_key_access_log_key_id_date', ['key_id', 'date'] ); 
            $this->renameUniqueIfLegacyNameExists( 'api_ip_key_count', 'ixcv', 'ux_api_ip_key_count_key_id_ip_id_date', ['key_id', 'ip_id', 'date'] );
            $this->renameUniqueIfLegacyNameExists( 'cache', 'idh', 'ux_cache_hash', ['hash'] ); 
            $this->renameUniqueIfLegacyNameExists( 'processes', 'idh_proc', 'ux_processes_hash', ['hash'] );
            $this->renameUniqueIfLegacyNameExists( 'cache', 'idh_cache', 'ux_cache_hash', ['hash'] );
            $this->renameUniqueIfLegacyNameExists( 'cache', 'idh_cache_long', 'ux_cache_hash_long', ['hash_long'] );
        } 
        
        public function down(): void 
        { 
            $this->renameUniqueIfLegacyNameExists( 'ip_access_log', 'ux_ip_access_log_ip_id_date', 'ixcv', ['ip_id', 'date'] ); 
            $this->renameUniqueIfLegacyNameExists( 'api_key_access_log', 'ux_api_key_access_log_key_id_date', 'ixcv', ['key_id', 'date'] ); 
            $this->renameUniqueIfLegacyNameExists( 'api_ip_key_count', 'ux_api_ip_key_count_key_id_ip_id_date', 'ixcv', ['key_id', 'ip_id', 'date'] ); 
            $this->renameUniqueIfLegacyNameExists( 'cache', 'ux_cache_hash', 'idh', ['hash'] );
            $this->renameUniqueIfLegacyNameExists( 'processes', 'ux_processes_hash', 'idh_proc', ['hash'] );
            $this->renameUniqueIfLegacyNameExists( 'cache', 'ux_cache_hash_long', 'idh_cache_long', ['hash_long'] );
        } 
            
        private function renameUniqueIfLegacyNameExists( string $tableName, string $fromIndex, string $toIndex, array $columns ): void 
        { 
            if (!Schema::hasTable($tableName)) { 
                return; 
            } 
            
            try { 
                Schema::table($tableName, function (Blueprint $table) use ($fromIndex): void { $table->dropUnique($fromIndex); }); 
            } catch (\Throwable $e) { 
                // Index likely does not exist on this database state. 
            } 
            
            try { 
                Schema::table($tableName, function (Blueprint $table) use ($columns, $toIndex): void { $table->unique($columns, $toIndex); }); 
            } catch (\Throwable $e) { 
                // Index likely already exists on this database state. 
            } 
        } 
    };