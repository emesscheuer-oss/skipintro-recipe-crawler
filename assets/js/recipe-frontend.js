(function($){
  function formatDE(n){
    // Zahl -> "1,5" etc.
    var v = parseFloat(n);
    if (isNaN(v)) return '';
    var s = v.toFixed(2).replace('.', ',');
    s = s.replace(/,00$/, '');
    s = s.replace(/,0$/, '');
    return s;
  }

  function recalc($root){
    var base = parseFloat($root.find('.sitc-ingredients').attr('data-base-servings')) || 1;
    var cur  = parseFloat($root.find('.sitc-servings').val()) || base;
    $root.find('.sitc-servings-display').text(formatDE(cur));

    $root.find('.sitc-ingredient').each(function(){
      var $li  = $(this);
      var qty  = $li.attr('data-qty');
      var unit = $li.attr('data-unit') || '';
      if (qty === '' || qty === null) return; // keine Zahl → unverändert

      var baseQty = parseFloat((qty+'').replace(',','.'));
      if (isNaN(baseQty)) return;

      var scaled = baseQty * (cur / base);
      $li.find('.sitc-qty').text(formatDE(scaled));
      if (unit) $li.find('.sitc-unit').text(' ' + unit);
    });
  }

  function toggleGrocery($btn){
    var target = $btn.attr('data-target');
    var $box = $(target);
    if (!$box.length) return;
    var expanded = $btn.attr('aria-expanded') === 'true';
    $btn.attr('aria-expanded', expanded ? 'false' : 'true');
    if (expanded) {
      $box.attr('hidden', true).addClass('collapsed');
    } else {
      $box.removeAttr('hidden').removeClass('collapsed');
    }
  }

  var wakeLock = null;
  async function toggleWake($btn){
    try{
      if (!('wakeLock' in navigator)) {
        alert('Wake Lock wird von diesem Browser nicht unterstützt.');
        return;
      }
      if (wakeLock){
        await wakeLock.release();
        wakeLock = null;
        $btn.removeClass('active');
        return;
      }
      wakeLock = await navigator.wakeLock.request('screen');
      $btn.addClass('active');
      wakeLock.addEventListener('release', () => {
        $btn.removeClass('active');
        wakeLock = null;
      });
    }catch(e){
      console.warn('Wake Lock error', e);
      alert('Konnte Wake Lock nicht aktivieren.');
    }
  }

  function trashPost(postId, token){
    if (!confirm(SITC_RECIPE.confirmTrash1)) return;
    if (!confirm(SITC_RECIPE.confirmTrash2)) return;

    $.post(SITC_RECIPE.ajax_url, {
      action: 'sitc_trash_post',
      post_id: postId,
      token: token
    }).done(function(resp){
      if (resp && resp.success){
        // Soft-Redirect: Seite neu laden
        location.reload();
      } else {
        alert(SITC_RECIPE.trashError);
      }
    }).fail(function(){
      alert(SITC_RECIPE.trashError);
    });
  }

  $(document).on('click', '.sitc-btn-plus', function(){
    var $root = $(this).closest('.sitc-recipe');
    var $inp  = $root.find('.sitc-servings');
    var v = parseFloat($inp.val()) || 1;
    $inp.val(Math.max(1, v + 1));
    recalc($root);
  });
  $(document).on('click', '.sitc-btn-minus', function(){
    var $root = $(this).closest('.sitc-recipe');
    var $inp  = $root.find('.sitc-servings');
    var v = parseFloat($inp.val()) || 1;
    $inp.val(Math.max(1, v - 1));
    recalc($root);
  });
  $(document).on('change', '.sitc-servings', function(){
    recalc($(this).closest('.sitc-recipe'));
  });

  // Checkbox → Durchstreichen via CSS-Klasse
  $(document).on('change', '.sitc-chk', function(){
    var $line = $(this).closest('.sitc-ingredient').find('.sitc-line');
    if (this.checked) $line.addClass('checked');
    else $line.removeClass('checked');
  });

  $(document).on('click', '.sitc-btn-grocery', function(){
    toggleGrocery($(this));
  });
  $(document).on('click', '.sitc-btn-wake', function(){
    toggleWake($(this));
  });
  $(document).on('click', '.sitc-btn-trash', function(){
    var postId = parseInt($(this).attr('data-post'), 10);
    var token  = $(this).attr('data-token') || '';
    if (!postId || !token) return;
    trashPost(postId, token);
  });

  // initial
  $(function(){
    $('.sitc-recipe').each(function(){ recalc($(this)); });
  });
})(jQuery);
