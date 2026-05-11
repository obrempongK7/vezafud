<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function conversations()
    {
        $convs = Conversation::with(['participants.user.profile','lastMessage'])->whereHas('participants',fn($q)=>$q->where('user_id',auth()->id()))->where('is_active',true)->orderByDesc('last_message_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$convs]);
    }

    public function messages(Request $request, string $uuid)
    {
        $conv = Conversation::where('uuid',$uuid)->firstOrFail();
        if (!$conv->participants()->where('user_id',auth()->id())->exists()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        $messages = $conv->messages()->with('sender.profile','replyTo')->orderByDesc('created_at')->paginate(30);
        // Mark as read
        $conv->participants()->where('user_id',auth()->id())->update(['last_read_at'=>now()]);
        return response()->json(['success'=>true,'data'=>$messages]);
    }

    public function startConversation(Request $request)
    {
        $request->validate(['user_id'=>'required|exists:users,id','message'=>'required|string|max:5000']);
        $authId = auth()->id();
        $targetId = $request->user_id;
        if ($authId === $targetId) return response()->json(['success'=>false,'message'=>'Cannot message yourself'],400);
        // Find existing direct conversation
        $existing = Conversation::where('type','direct')->whereHas('participants',fn($q)=>$q->where('user_id',$authId))->whereHas('participants',fn($q)=>$q->where('user_id',$targetId))->first();
        if ($existing) {
            $msg = $this->sendMessageToConv($existing,$authId,$request->message);
            return response()->json(['success'=>true,'data'=>['conversation'=>$existing,'message'=>$msg]]);
        }
        DB::transaction(function() use($authId,$targetId,$request,&$conv,&$msg) {
            $conv = Conversation::create(['uuid'=>Str::uuid(),'type'=>'direct','last_message_at'=>now()]);
            $conv->participants()->createMany([['user_id'=>$authId,'role'=>'member'],['user_id'=>$targetId,'role'=>'member']]);
            $msg = $this->sendMessageToConv($conv,$authId,$request->message);
        });
        return response()->json(['success'=>true,'data'=>['conversation'=>$conv->load('participants.user.profile'),'message'=>$msg]],201);
    }

    public function send(Request $request, string $uuid)
    {
        $request->validate(['message'=>'required_without:media|nullable|string|max:5000','media'=>'nullable|file|max:51200']);
        $conv = Conversation::where('uuid',$uuid)->firstOrFail();
        if (!$conv->participants()->where('user_id',auth()->id())->exists()) return response()->json(['success'=>false,'message'=>'Unauthorized'],403);
        $msg = $this->sendMessageToConv($conv,auth()->id(),$request->message,$request->file('media'));
        return response()->json(['success'=>true,'data'=>$msg]);
    }

    private function sendMessageToConv(Conversation $conv, int $senderId, ?string $text, $media = null): Message
    {
        $mediaData = null;
        if ($media) {
            $path = $media->store("messages/{$conv->id}",'public');
            $mediaData = ['url'=>asset('storage/'.$path),'type'=>str_contains($media->getMimeType(),'image')?'image':'video'];
        }
        $msg = $conv->messages()->create(['uuid'=>Str::uuid(),'sender_id'=>$senderId,'message_type'=>$media?'image':'text','body'=>$text,'media'=>$mediaData]);
        $conv->update(['last_message_id'=>$msg->id,'last_message_at'=>now()]);
        broadcast(new \App\Events\MessageSent($conv->id,$msg));
        return $msg->load('sender.profile');
    }

    public function deleteMessage(Message $message)
    {
        if ($message->sender_id !== auth()->id()) return response()->json(['success'=>false],403);
        $message->update(['is_deleted'=>true,'body'=>null,'media'=>null]);
        return response()->json(['success'=>true]);
    }
}
