<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Http;

use MacropaySolutions\Framework\Routing\Controller;
use MacropaySolutions\Kernel\Http\Request;

class SessionTestController extends Controller
{
    public function put(Request $request): string
    {
        $request->session()->put('cart', (string)$request->input('item'));
        $request->session()->flash('status', 'added');

        return 'ok';
    }

    public function get(Request $request): string
    {
        return \json_encode([
            'cart' => $request->session()->get('cart'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function token(Request $request): string
    {
        return (string)$request->session()->token();
    }

    public function submit(): string
    {
        return 'submitted';
    }

    public function setCookie(): \MacropaySolutions\Kernel\Http\Response
    {
        return \response('cookie set')->withCookie(\cookie('flavour', 'chocolate', 10));
    }

    public function readCookie(Request $request): string
    {
        return (string)$request->cookie('flavour');
    }
}
