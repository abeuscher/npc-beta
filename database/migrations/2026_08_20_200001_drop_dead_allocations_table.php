<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Session 398 (Plugin Architecture arc D8, front-loaded decision 4, owner-
// ratified): the allocations table is dead schema — zero readers (the only
// mention anywhere in code is a comment in CascadeDeleteTest) and zero rows
// on every known install; it was never documented (no docs/schema file, no
// README row). Its three FKs were the only inbound references into the
// product tables, so this drop is what makes the D8 squash-boundary redraw
// zero-inbound. Dropped from the core dump in the same commit (348-precedent
// identity check: the dump diff is exactly the allocations DDL — the 396
// dead-posts precedent). Guarded so the migration is a no-op on databases
// where the table never existed (fresh installs load the already-
// allocations-free dump first).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('allocations');
    }

    public function down(): void
    {
        // Irreversible by design — the table was dead schema with no readers
        // and no rows; nothing can recreate a reason for it to exist.
    }
};
