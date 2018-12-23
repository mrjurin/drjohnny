<?php

namespace App\Conversations;

use App\Symptom;
use App\SymptomVariant;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;

class DiagnoseConversation extends Conversation
{
    const MAX_BUTTONS = 5;

    protected $symptomIds = [];

    protected $symptomNames = [];

    public function run()
    {
        $this->askUserSymptoms();
    }

    protected function askUserSymptoms()
    {
        $question = Question::create('Silakan pilih salah satu gejala di bawah ini, atau ketik saja 😊')
            ->addButtons($this->createSymptomQuestionButtons());

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $symptomName = $answer->getMessage()->getPayload()['message']['text'];

                $this->symptomIds[] = (int) $answer->getValue();
                $this->symptomNames[] = strtolower($symptomName);
            } else {
                $this->findSymptom($answer->getText());
            }

            $this->askAnythingElse();
        });
    }

    protected function createSymptomQuestionButtons()
    {
        $buttons = [];

        $symptoms = Symptom::query()
            ->whereNotIn('id', $this->symptomIds)
            ->inRandomOrder()
            ->take(5)
            ->cursor();

        foreach ($symptoms as $symptom) {
            $variant = $symptom->variants()->inRandomOrder()->first();

            $buttons[] = Button::create($variant->name)->value($symptom->id);
        }

        return $buttons;
    }

    protected function askAnythingElse()
    {
        $this->say('Baik, saat ini keluhan Anda adalah: ' . implode(', ', $this->symptomNames) . ' 📝');

        $question = Question::create('Adakah gejala lain yang ingin Anda bagikan? 😊')
            ->addButtons([
                Button::create('Ya')->value('yes'),
                Button::create('Tidak')->value('no'),
            ]);

        $this->ask($question, function (Answer $answer) {
            $answerText = $answer->isInteractiveMessageReply() ? $answer->getValue() : $answer->getText();

            if (strtolower($answerText) === 'yes' || strtolower($answerText) === 'ya') {
                $this->askUserSymptoms();
            } else {
                $this->say('Mantap!');
                $this->say(json_encode($this->symptomIds));
            }
        });
    }

    protected function findSymptom($search)
    {
        $variantName = remove_stop_words($search);

        $symptomVariant = SymptomVariant::search($variantName)->first();

        if ($symptomVariant) {
            $this->symptomIds[] = $symptomVariant->symptom->id;
        }

        $this->symptomNames[] = strtolower($search);
    }
}
