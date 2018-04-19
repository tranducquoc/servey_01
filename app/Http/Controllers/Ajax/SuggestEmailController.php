<?php

namespace App\Http\Controllers\Ajax;

use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\User\UserInterface;

class SuggestEmailController extends Controller
{
    protected $userRepository;

    public function __construct(UserInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function suggestEmail(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json([
                'success' => false,
            ]);
        }

        $data = $request->only('keyword', 'emails');
        $emails = $this->userRepository->findEmail($data, Auth::user()->id);

        return response()->json([
            'success' => true,
            'emails' => $emails,
        ]);
    }
}
