<div id="wrapper">
 <h1>Displaying single post</h1>
   <div class="post">
    <div class="actions"><a href="posts.php?mvcmethod=delete&amp;id=<?=$post->id?>">Delete</a></div>
    <div class="message"><?=$post->message?></div>
    <div class="time"><?=$post->posted?></div>
   </div>
</div>
