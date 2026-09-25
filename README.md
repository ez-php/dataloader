# ez-php/dataloader

Generic keyed-batch loading and per-key memoization. Zero external dependencies — pure PHP.

Collect every key requested during a single tick, resolve them all in one call to a
batch function, then distribute each result back and cache it per key. Framework-agnostic:
useful anywhere an N+1 access pattern shows up — GraphQL resolvers, REST "include" params,
template loops — not tied to any particular resolver layer.

## Usage

```php
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\DataLoader\DataLoader;

/** @var DatabaseInterface $db e.g. injected into the class that builds the loader */
$userLoader = new DataLoader(function (array $ids) use ($db): array {
    // one query for every id requested since the last dispatch — one placeholder per id
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $rows = $db->query("SELECT * FROM users WHERE id IN ($placeholders)", array_values($ids));

    return array_column($rows, null, 'id'); // keyed by id, as the contract below requires
});

$a = $userLoader->load(1);
$b = $userLoader->load(2);
$c = $userLoader->load(1); // same key, deduplicated — no extra work queued

$a->get(); // triggers dispatch(): one batch call for [1, 2], then returns user 1
$b->get(); // already resolved by the same dispatch
$c->get(); // === $a->get(), from cache — no further batch calls
```

`load()` never calls the batch function itself — it just queues the key (or returns the
cached/pending `Deferred` for one already queued) and returns a `Deferred`. The batch
function only runs when something calls `Deferred::get()` for an unresolved key, or when
`DataLoader::dispatch()` is called directly.

### Batch load function contract

```php
/**
 * @param list<int|string> $keys
 * @return array<int|string, mixed> a value for every key given
 */
```

If a key you queued isn't present in the returned array, that key's `Deferred::get()`
throws `EzPhp\DataLoader\Exception\MissingKeyException`.

### Cache control

```php
$userLoader->prime(5, $alreadyFetchedUser); // seed the cache without a batch call
$userLoader->clear(5);                      // drop one cached key
$userLoader->clearAll();                    // drop everything
```

Pass `useCache: false` to the constructor to disable memoization and re-run the batch
function for every key on every dispatch.

## Installation

```bash
composer require ez-php/dataloader
```

## Testing

```bash
composer full
```
