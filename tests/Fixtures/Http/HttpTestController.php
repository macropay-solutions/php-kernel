<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Framework\Routing\Controller;
use MacropaySolutions\Kernel\Http\JsonResponse;
use MacropaySolutions\Kernel\Http\Request;

class HttpTestController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return \response()->json(['id' => $request->user()->getAuthIdentifier()]);
    }

    public function echo(Request $request): JsonResponse
    {
        return \response()->json($request->all());
    }

    public function show(string $id): JsonResponse
    {
        return \response()->json(['id' => $id]);
    }

    public function forbidden(): void
    {
        \abort(403, 'Not yours.');
    }

    public function authorizeInController(): string
    {
        $this->authorize('edit-post', [7]);

        return 'edited';
    }
}
