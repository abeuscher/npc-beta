<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Session 396 (Plugin Architecture arc D6, front-loaded decision 3, owner-
// ratified): the posts table is dead schema — zero readers (every "posts"
// reference in app/ is Page-typed; posts are Page rows of type 'post') and
// zero rows on every known install. It predates the pages-as-posts CMS shape.
// Dropped from the core dump in the same commit (348-precedent identity
// check: the dump diff is exactly the posts DDL). Guarded so the migration is
// a no-op on databases where the table never existed (fresh installs load the
// already-posts-free dump first).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('posts');
    }

    public function down(): void
    {
        // Irreversible by design — the table was dead schema with no readers
        // and no rows; nothing can recreate a reason for it to exist.
    }
};
