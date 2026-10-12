<?php

namespace App\Http\Controllers;

use App\Support\KnowledgeBase;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('KnowledgeBase/Index', [
            'articles' => KnowledgeBase::articles(),
        ]);
    }
}
