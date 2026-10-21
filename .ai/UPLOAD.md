# File uploads

Read when an operation receives a file: a `File` property, a
`multipart/form-data` request. The conventions of the module are in
[`CONTEXT.md`](./CONTEXT.md).

## Request format and method

- The file travels in a `multipart/form-data` request, the only format
  carrying a binary part: `inputFormats` lists `multipart`.
- PHP only fills the uploaded files of a POST request, so an operation
  receiving a file is a POST, even when it updates (ADR PR #45, §HTTP
  methods). The update that may carry a file is a `CQRSUpdate` with
  `method: CQRSUpdate::METHOD_POST`, `status: Response::HTTP_OK` and
  `inputFormats` limited to `multipart`; it is partial or full like its
  command, and its summary and description say that it updates
  ([`OPENAPI.md`](./OPENAPI.md)).
- When that update is partial, a JSON `PATCH` on the same URI updates
  the fields without the file, for a client with nothing to upload: the
  file field is left out of its input. A full update needs the file, so
  it has no JSON variant.
- A creation is a POST anyway: it accepts JSON when the file is
  optional, and multipart when a file is sent (`inputFormats` lists
  `json` and `multipart`). An operation that always needs the file is
  multipart only, and a JSON request answers 415.
- Older updates predate the split: `Carrier` and `VirtualProductFile`
  accept JSON on their multipart POST, `ProductImage` takes multipart
  only. Their JSON `PATCH` comes with the refactoring of their domain.
- The other fields are form fields of the same request (`ProductImage`:
  `legends`, `cover`, `position`). Form values are strings: the operation
  sets `denormalizationContext: [ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT
  => true]` and the response carries the real types. Nested values use
  the bracket syntax (`delays[en-US]=…`, `zones[]=1`) on every core; a
  part holding a JSON array or object (`legends={"en-US":"…"}`, the
  Swagger UI encoding) is decoded from 9.2.0.
- A boolean form field is sent as `1` / `0`, the accepted compromise: a
  form value is coerced, not parsed, so a `false` string would read as
  true. Tests send `1` / `0`.

## File property and command

- The file is a public property typed
  `Symfony\Component\HttpFoundation\File\File`, named after the form
  part. The core documents a non-nullable `File` as a required part of
  every multipart operation of the class, so:
  - not nullable when all of them need the file: a resource dedicated to
    the creation, an update requiring every field;
  - nullable with a `null` default (`?File $logo = null`) when a partial
    update accepts the request without it, or when other operations of a
    larger class do not take it (`ProductImage::$image` and
    `VirtualProductFile::$file` are not nullable yet, so their partial
    updates document the file as required).
  An operation needing the nullable file adds `NotNull` with its
  validation group. Its documentation still shows the part as optional:
  the core reads the requirement from the nullability only, and a
  request body override replaces the generated body, so its description
  says that the file is required.
- The file is write-only: `#[ApiProperty(readable: false, writable:
  false)]` keeps it out of the JSON schemas, and the core documents it as
  a binary part of the multipart body (from 9.2.0, nothing before). A
  PHP comment says which operations take it and how the response exposes
  the stored file (a URL, a file name). Without the attribute, the read
  schema lists the file (`Carrier::$logo`).
- `Assert\File` (or `Assert\Image`) carries the size and types of the
  back-office form (`Carrier::$logo`: `maxSize: '8M'`, `image/jpeg`).
  It only checks a sent file, so it stays in `Default`.
- The command takes the path of a local file, the temporary upload: the
  command mapping reads it from the file through a property path,
  `'[logo].pathName' => '[logoPathName]'` (`File::getPathname()`). The
  handler copies the file to its storage and removes the temporary one.
  A legacy command taking the `UploadedFile` itself (`AddTitleCommand`)
  receives the property under its own name, without mapping; a new
  command takes a path.

## Tests

On top of [`TESTING.md`](./TESTING.md):

- A multipart request goes through `requestApi()` with the
  `content-type: multipart/form-data` header and `extra`: `parameters`
  (string values, bracket syntax) and `files`, built by
  `prepareUploadedFile()` from an asset of `tests/Resources/assets/` (a
  temporary copy, since the API removes the uploaded file).
- Covered: the upload (whole response, real types, the stored file or
  its exposed URL); the JSON `PATCH` of the fields; a JSON creation when
  the file is optional; a 415 for a JSON request on a multipart-only
  operation; an invalid file (422 on the file property path,
  `CarrierEndpointTest`); the validation errors of the other fields sent
  as form values.
- Stored files survive the database restore of the suite: remove them in
  `tearDownAfterClass()` (`CarrierEndpointTest`).
- A core whose handler stores the file with `move_uploaded_file()`
  stores nothing for a test upload, which is not a real HTTP upload: the
  stored-file assertions are skipped below the fixed core
  (`markTestSkippedByMinVersion('9.2.0')` for the carrier logo).

## Canonical examples

- `src/ApiPlatform/Resources/Carrier/Carrier.php` and
  `CarrierEndpointTest` — JSON or multipart creation, optional
  `?File $logo = null`, the constraint of the back-office form,
  `[logo].pathName` mapping, stored-file assertions and cleanup; its
  update predates the JSON `PATCH` rule.
- `src/ApiPlatform/Resources/Product/VirtualProductFile.php` and
  `VirtualProductFileEndpointTest` — multipart-only create (415 on
  JSON), the file kept out of the JSON schemas, `NotNull` on the create
  only; its update predates the JSON `PATCH` rule.
- `src/ApiPlatform/Resources/Product/ProductImage.php` — other fields
  sent as form values with the image (localized legends, cover,
  position).
