<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\Result\ResultInterface;
use App\Repositories\Survey\SurveyInterface;
use App\Http\Controllers\Controller;
use App\Repositories\Invite\InviteInterface;
use App\Repositories\Setting\SettingInterface;
use App\Http\Requests\AnswerRequest;
use Carbon\Carbon;
use Exception;
use DB;

class ResultController extends Controller
{
    protected $resultRepository;
    protected $surveyRepository;
    protected $inviteReposirory;
    protected $settingReposirory;

    public function __construct(
        ResultInterface $resultRepository,
        SurveyInterface $surveyRepository,
        InviteInterface $inviteReposirory,
        SettingInterface $settingReposirory
    ) {
        $this->resultRepository = $resultRepository;
        $this->surveyRepository = $surveyRepository;
        $this->inviteReposirory = $inviteReposirory;
        $this->settingReposirory = $settingReposirory;
    }

    public function result($token, AnswerRequest $request)
    {
        $isSuccess = false;
        $answers = $request->get('answer');
        $data = [];
        $survey = $this->surveyRepository->where('token', $token)->first();
        $invite = $this->inviteReposirory
            ->where([
                'recevier_id' => auth()->id(),
                'survey_id' => $survey->id,
            ])
            ->orWhere(function ($query) use ($survey) {
                $query->where([
                    'survey_id' => $survey->id,
                    'mail' => (auth()->id()) ? auth()->user()->email : null,
                ]);
            })
            ->first();

        if ($survey->feature
            || (!$survey->feature && auth()->id() && $invite)
            || auth()->id() == $survey->user_id
        ) {
            foreach ($answers as $answer) {
                if (!is_array($answer)) {
                    $answer = [$answer => null];
                }

                foreach ($answer as $key => $value) {
                    //  Set default email and name if user not login or don't have setting require email, name or both.
                    if (!auth()->check() && !$request->get('name-answer') && !$request->get('email-answer')) {
                        $setName = config('settings.name_unidentified');
                        $setEmail = config('settings.email_unidentified');
                    } else {
                        $setName = $request->get('name-answer') ?: (
                            auth()->check() ? auth()->user()->name : config('settings.name_unidentified')
                        );
                        $setEmail = $request->get('email-answer') ?: (
                            auth()->check() ? auth()->user()->email: config('settings.email_unidentified')
                        );
                    }

                    $data[] = [
                        'sender_id' => auth()->id(),
                        'recevier_id' => $survey->user_id,
                        'answer_id' => $key,
                        'content' => $value,
                        'name' => $setName,
                        'email' => $setEmail,
                        'created_at' => Carbon::now(),
                    ];
                }
            }

            $isSuccess = true;
        }

        DB::beginTransaction();
        try {
            if (!empty($data)
                && $this->resultRepository->multiCreate($data)
            ) {

                $decreaseNumber = $this->settingReposirory
                    ->where([
                        'survey_id' => $survey->id,
                        'key' => config('settings.key.limitAnswer'),
                    ])
                    ->first();

                if ($decreaseNumber && $decreaseNumber->value) {
                    $decreaseNumber->update(['value' => --$decreaseNumber->value]);
                }

                if ($invite && $invite->status) {
                    $isSuccess = $invite->update(['status' => config('survey.invite.old')]);
                }
            }

            if (!$isSuccess) {
                throw new Exception;
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
        }

        return redirect()
            ->action(($survey->feature) ? 'AnswerController@answerPublic' : 'AnswerController@answerPrivate', $survey->token)
            ->with(($isSuccess) ? 'message' : 'message-fail', ($isSuccess)
                ? trans('messages.object_created_successfully', [
                    'object' => class_basename(Answer::class),
                ])
                : trans('generate.permisstion', [
                    'object' => class_basename(Answer::class),
                ]));
    }
}
