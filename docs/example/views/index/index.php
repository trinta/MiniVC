<div id="wrapper">
 <div id="postform">
 <h1>Post some stuff!</h1>
 
 <form method="post" action="posts.php?mvcmethod=post">
  <div>
   <div style="float: right;"><input type="submit" name="submit" value="Post"/></div>
   <div><textarea name="message" style="width: 80%;"></textarea></div>
  </div>
 </form>
 </div>
 
 <div id="posts">
  <?foreach($posts as $post):?>
   <div class="post">
    <div class="actions"><a href="posts.php?mvcmethod=delete&amp;id=<?=$post->id?>">Delete</a></div>
    <div class="message"><?=$post->message?></div>
    <div class="time"><?=$post->posted?></div>
   </div>
  <?endforeach;?>
 </div>
</div>