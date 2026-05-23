# Laravel Inertia Block Editor

Reusable Laravel package for building a block editor with Inertia.js and React. It provides the backend storage layer, validation rules, services, and publishable React components needed to add a nested block editor to Laravel applications.

## Features

- Polymorphic `content_blocks` migration
- `HasBlocks` trait for models such as `Post`, `Page`, `Product`, or custom content models
- `BlockTreeService` for saving and loading nested block trees
- `ValidBlocks` validation rule
- Optional JSON controller route for saving block trees
- Publishable React components: `BlockBuilder`, `BlockRenderer`, `BlockEditor`, and `StructureTree`
- Basic blocks: Heading, Paragraph, Rich Editor, Image, Button, Icon, Divider, Spacer, and Table
- Layout blocks: Section, Container, Grid, Columns, Flex Row, Flex Column, Card, Tabs, Accordion, and Slider

## 1. Installation

For local development, add the package as a path repository:

```bash
composer config repositories.inertia-block-editor path packages/jamalapr/laravel-inertia-block-editor
composer require jamalapr/laravel-inertia-block-editor:@dev
```

After publishing the package to GitHub or Packagist, install it with:

```bash
composer require jamalapr/laravel-inertia-block-editor
```

Install the required frontend dependencies in the host application:

```bash
npm install @dnd-kit/core @dnd-kit/sortable lucide-react
```

## 2. Publish Config, Migration, And React Assets

```bash
php artisan vendor:publish --tag=inertia-block-editor-config
php artisan vendor:publish --tag=inertia-block-editor-migrations
php artisan vendor:publish --tag=inertia-block-editor-react
php artisan migrate
```

The React assets will be published to:

```txt
resources/js/vendor/inertia-block-editor
```

## 3. Database Schema

The package uses a polymorphic table, so blocks can belong to any Eloquent model.

```php
Schema::create(config('inertia-block-editor.table', 'content_blocks'), function (Blueprint $table) {
    $table->id();
    $table->morphs('blockable');
    $table->foreignId('parent_id')->nullable()->constrained(config('inertia-block-editor.table', 'content_blocks'))->cascadeOnDelete();
    $table->string('type');
    $table->json('props')->nullable();
    $table->json('styles')->nullable();
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index(['blockable_type', 'blockable_id', 'parent_id', 'sort_order'], 'content_blocks_tree_index');
});
```

Because the table uses `morphs`, the same schema works for:

- `Post`
- `Page`
- `Product`
- `LandingPage`
- Any custom Eloquent model

## 4. Add The Trait To A Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Jamalapr\InertiaBlockEditor\Concerns\HasBlocks;

class Post extends Model
{
    use HasBlocks;
}
```

## 5. Validate Block Payloads

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Jamalapr\InertiaBlockEditor\Rules\ValidBlocks;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'blocks' => ['nullable', new ValidBlocks],
            'status' => ['required', 'in:draft,publish'],
        ];
    }
}
```

The `blocks` field may be submitted as an array or a JSON string.

## 6. Save And Edit Blocks From A Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Jamalapr\InertiaBlockEditor\Services\BlockTreeService;

class PostController
{
    public function create()
    {
        return Inertia::render('Posts/Create');
    }

    public function store(StorePostRequest $request, BlockTreeService $blocks)
    {
        $data = $request->validated();

        $post = Post::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'status' => $data['status'],
        ]);

        $blocks->sync($post, $data['blocks'] ?? []);

        return redirect()->route('posts.edit', $post);
    }

    public function edit(Post $post, BlockTreeService $blocks)
    {
        return Inertia::render('Posts/Edit', [
            'post' => $post,
            'blocks' => $blocks->tree($post),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post, BlockTreeService $blocks)
    {
        $data = $request->validated();

        $post->update([
            'title' => $data['title'],
            'status' => $data['status'],
        ]);

        $blocks->sync($post, $data['blocks'] ?? []);

        return redirect()->route('posts.edit', $post);
    }
}
```

## 7. Use The Editor In An Inertia React Page

Example `resources/js/pages/Posts/Create.tsx`:

```tsx
import { useForm } from "@inertiajs/react";
import { BlockBuilder } from "@/vendor/inertia-block-editor";
import type { BlockInstance } from "@/vendor/inertia-block-editor";

export default function Create() {
  const { data, setData, post, processing } = useForm<{
    title: string;
    status: string;
    blocks: BlockInstance[];
  }>({
    title: "",
    status: "draft",
    blocks: [],
  });

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        post("/posts");
      }}
      className="min-h-screen"
    >
      <header className="flex items-center gap-3 border-b p-4">
        <input
          value={data.title}
          onChange={(event) => setData("title", event.target.value)}
          className="h-10 flex-1 rounded border px-3"
          placeholder="Post title"
        />

        <button
          disabled={processing}
          className="rounded bg-black px-4 py-2 text-white"
        >
          Save
        </button>
      </header>

      <BlockBuilder
        value={data.blocks}
        onChange={(blocks) => setData("blocks", blocks)}
        className="border-0"
      />
    </form>
  );
}
```

Example `Edit.tsx`:

```tsx
import { useForm } from "@inertiajs/react";
import { BlockBuilder } from "@/vendor/inertia-block-editor";
import type { BlockInstance } from "@/vendor/inertia-block-editor";

export default function Edit({
  post,
  blocks,
}: {
  post: any;
  blocks: BlockInstance[];
}) {
  const { data, setData, put, processing } = useForm({
    title: post.title,
    status: post.status,
    blocks,
  });

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        put(`/posts/${post.id}`);
      }}
    >
      <input
        value={data.title}
        onChange={(event) => setData("title", event.target.value)}
      />

      <BlockBuilder
        value={data.blocks}
        onChange={(nextBlocks) => setData("blocks", nextBlocks)}
      />

      <button disabled={processing}>Update</button>
    </form>
  );
}
```

## 8. Optional Generic Save Endpoint

Enable the built-in route in `config/inertia-block-editor.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'dashboard/block-editor',
    'name' => 'block-editor.',
    'middleware' => ['web', 'auth'],
],

'blockables' => [
    'post' => App\Models\Post::class,
    'page' => App\Models\Page::class,
],
```

Then send a request like this:

```ts
await fetch("/dashboard/block-editor/blocks", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-CSRF-TOKEN":
      document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") ?? "",
  },
  body: JSON.stringify({
    blockable_type: "post",
    blockable_id: 1,
    blocks,
  }),
});
```

## 9. Render Blocks On A Public Page

```tsx
import { BlockRenderer } from "@/vendor/inertia-block-editor";
import type { BlockInstance } from "@/vendor/inertia-block-editor";

export default function Show({ blocks }: { blocks: BlockInstance[] }) {
  return (
    <main>
      {blocks.map((block) => (
        <BlockRenderer key={block.id} block={block} />
      ))}
    </main>
  );
}
```

## 10. Custom Block Types

Add the new block type to the package config:

```php
'allowed_types' => [
    // ...
    'hero-banner',
],
```

Then edit the published React registry:

```txt
resources/js/vendor/inertia-block-editor/editor/blocks/registry.ts
```

Register the new block component so it can be rendered and edited.
