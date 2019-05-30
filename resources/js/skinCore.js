$(document).ready(function(){
  /* Initialize Variables */
  var isSlim = false;
  var skinURL;

  /* OnSubmit uploadSkinForm */
  $("#uploadSkinForm").on("submit", function(e){
    // Swal.showLoading();
    e.preventDefault();
    var formData = new FormData($("#uploadSkinForm")[0]);
    $.ajax({
      type : "POST",
      url : "resources/server/skinCore.php",
      cache : false,
      contentType : false,
      processData : false,
      data : formData,
      dataType : "JSON",
      encode : true,
    }).done(function(res){
      Swal.fire(res);
      if (typeof(res.refresh) === 'number') {
        setTimeout(function(){ location.reload(); }, res.refresh);
      };
    }).fail(function(){
      console.log("[ERROR] AJAX FAILED!");
    });
  });

  /* OnSubmit uploadSkinForm */
  // $("#uploadSkinForm").on("submit", function(e){
  //   e.preventDefault();
  //   var formData = new FormData($("#uploadSkinForm")[0]);
  //   $.ajax({
  //     type : "POST",
  //     url : "resources/server/skinCore.php",
  //     cache : false,
  //     contentType : false,
  //     processData : false,
  //     data : formData,
  //     dataType : "JSON",
  //     encode : true,
  //   }).done(function(res){
  //     Swal.fire(res);
  //     if (typeof(res.refresh) === 'number') {
  //       setTimeout(function(){ location.reload(); }, res.refresh);
  //     };
  //   }).fail(function(){
  //     console.log("[ERROR] AJAX FAILED!");
  //   });
  // });

  /* If user changes uploadtype */
  $("[id^=uploadtype-]").on("change", function(){
    ['file', 'url'].forEach(function(nm) {
      if ($("#uploadtype-"+nm)[0].checked == true) {
        $("#form-input-"+nm).show();
        $("#input-"+nm).prop("required", true);
        $("#input-"+nm).trigger("change");
        $("#input-"+nm).trigger("input");
      }
      else {
        $("#form-input-"+nm).hide();
        $("#input-"+nm).prop("required", false);
      }
    });
  });
  if ($("#skinViewerContainer").length) {
    /* Initialize MineRender */
    var skinRender = new SkinRender({
      autoResize : true,
      controls : {
        enabled : false,
        zoom : false,
        rotate : false,
        pan : false
      },
      canvas : {
        height : $("#skinViewerContainer")[0].offsetHeight,
        width : $("#skinViewerContainer")[0].offsetWidth
      },
      camera : {
        x : 15,
        y : 25,
        z : 24,
        target: [0, 17, 0]
      }
    }, $("#skinViewerContainer")[0]);
  
    /* Add some animate to a model in SkinPreview */
    var startTime = Date.now();
    var t;
    $("#skinViewerContainer").on("skinRender", function(e){
      if(!e.detail.playerModel){ return; }
      e.detail.playerModel.rotation.y += 0.01;
      t = (Date.now() - startTime) / 1000;
      e.detail.playerModel.children[2].rotation.x = Math.sin(t * 5) / 2;
      e.detail.playerModel.children[3].rotation.x = -Math.sin(t * 5) / 2;
      e.detail.playerModel.children[4].rotation.x = Math.sin(t * 5) / 2;
      e.detail.playerModel.children[5].rotation.x = -Math.sin(t * 5) / 2;
    });
  }

  // fetch default skin data of user
  function renderUser() {
    if ($("#account-name").length) {username = $("#account-name").attr("name");} // if logged in with authme
    if ($("#input-username").length) {username = $("#input-username").val();} // if selected by textbox
    skinURL = 'resources/server/skinRender.php?format=raw&user='+username;
    render(true, true);
  }

  /* RENDER FUNCTION */
  var rendDelay;
  function render(checkskin=true, delay=false){
    if (!skinURL) { renderUser(); return; }
    if (delay) {
      if (rendDelay) {window.clearTimeout(rendDelay);}
      rendDelay = window.setTimeout(render, 500);
    }
    else {
      if (checkskin) {
        skinChecker(function(){
          console.log('slimness: '+isSlim);
          $("#skintype-alex").prop("checked", isSlim);
          $("#skintype-steve").prop("checked", !isSlim);
          render(false, delay)
        });
      }
      else if ($("#skinViewerContainer").length) {
        if ($('[id^=minerender-canvas-]')[0]) {
          skinRender.clearScene();
        }
        skinRender.render({
          url : skinURL,
          slim : isSlim
        });
      }
    }
  }

  /* Check what type of skin (Alex or Steve) */
  function skinChecker(callback){
    var image = new Image();
    image.crossOrigin = "Anonymous";
    image.src = skinURL;
    image.onload = function(){
      var detectCanvas = document.createElement("canvas");
      var detectCtx = detectCanvas.getContext("2d");
      detectCanvas.width = image.width;
      detectCanvas.height = image.height;
      detectCtx.drawImage(image, 0, 0);
      var bgpx = {4:{16:{4:[0,52],8:[12,36]},32:{4:[0,52],8:[12,36]},48:{4:[0,60],8:[12,28,44]}},8:{0:{8:[0,56],16:[24]}}};
      var bgclrs = {};
      for (var a in bgpx){
        for (var b in bgpx[a]){
          for (var c in bgpx[a][b]){
            for (var d in bgpx[a][b][c]){
              var d = bgpx[a][b][c][d];
              var pxgr = detectCtx.getImageData(d, b, c, a).data;
              for (var i = 0; i < pxgr.length; i+=4) {
                var clr = pxgr[i].toString()+pxgr[(i+1)].toString()+pxgr[(i+2)].toString()+pxgr[(i+3)].toString();
                if (clr in bgclrs) {
                  bgclrs[clr]++;
                } else {
                  bgclrs[clr] = 1;
                }}}}}}
      // primary background color from background pixel list
      var bgclr = Object.keys(bgclrs).reduce(function(a, b){ return bgclrs[a] > bgclrs[b] ? a : b });
      var allSame = true;
      var px = [detectCtx.getImageData(46, 52, 1, 12).data, detectCtx.getImageData(54, 20, 1, 12).data];
      dance:
      for (var s = 0; s < px.length; s++){ // each arm pixel array
        for(var i = 0; i < 12 * 4; i += 4){ // each pixel in arm array
          // console.log('allSame rgba: '+px[s][(i+3)]+" and "+px[s][i]);
          // if a pixel doesn't match the primary background color, then it's not an alex skin
          if(bgclr !== px[s][i].toString()+px[s][(i+1)].toString()+px[s][(i+2)].toString()+px[s][(i+3)].toString()){
            var allSame = false;
            break dance;
          }
        }
      }
      isSlim = allSame;
      if(callback !== undefined){ callback(); }
    }
  }

  /* Display user's current skin */
  renderUser(); // if logged in with authme
  $("#input-username").on("input", function(e) { // if selected by textbox
    renderUser();
  });
  
  /* If user changes skin file */
  $("#input-file").on("change", function(event){
    if($("#input-file")[0].files.length === 0){ return; }
    skinURL = URL.createObjectURL(event.target.files[0]);
    render();
  });

  /* If user changes skin URL */
  $("#input-url").on("input", function(){
    // console.log('input url content is: '+$("#input-url").val());
    pth = "/resources/server/skinRender.php?format=raw&user="
    skinURL = $("#input-url").val().replace(/^(?:(\w+)@(.+?)\/?)$/g, '$2'+pth+'$1').replace(/^(\w+)$/g, $(location).attr('href').replace(/\/+$/g, "")+pth+'$1');
    render(true, true);
    if(!$("#input-url").val()){ return; }
  });

  /* If user changes skintype radios */
  $("[id^=skintype-]").on("change", function(){
    isSlim = !isSlim;
    render(false);
  });

  /* Change file name when user changes skin file input */
  $(".custom-file-input").on("change", function(){
    var fileName = $(this).val().split("\\").pop();
    if(fileName){
      $(this).next(".custom-file-label").html(fileName);
      console.log("File selected : " + fileName);
    } else {
      $(this).next(".custom-file-label").html("Choose skin...");
      console.log("No file selected!");
    }
  });
});
