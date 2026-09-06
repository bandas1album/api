(function ($) {
  'use strict'

  if (typeof bandasMediaFromUrl === 'undefined') {
    return
  }

  var i18n = bandasMediaFromUrl.i18n || {}

  function getPostId() {
    if (
      window.wp &&
      wp.media &&
      wp.media.view &&
      wp.media.view.settings &&
      wp.media.view.settings.post &&
      wp.media.view.settings.post.id
    ) {
      return parseInt(wp.media.view.settings.post.id, 10) || 0
    }
    var $postId = $('#post_ID')
    if ($postId.length) {
      return parseInt($postId.val(), 10) || 0
    }
    return 0
  }

  function setMessage($root, text, type) {
    var $msg = $root.find('.bandas-media-from-url__msg')
    $msg
      .removeClass('is-error is-success')
      .addClass(type ? 'is-' + type : '')
      .text(text || '')
  }

  function selectInOpenFrame(attachmentData) {
    if (!window.wp || !wp.media || !wp.media.frame) {
      return false
    }

    var frame = wp.media.frame
    var attachment = wp.media.model.Attachment.get(attachmentData.id)
    attachment.set(attachmentData)

    try {
      var library = frame.state().get('library')
      if (library && typeof library.add === 'function') {
        library.add(attachment)
      }
    } catch (e) {
      /* ignore */
    }

    try {
      var selection = frame.state().get('selection')
      if (selection && typeof selection.reset === 'function') {
        selection.reset([attachment])
      }
    } catch (e) {
      /* ignore */
    }

    try {
      if (frame.content && typeof frame.content.mode === 'function') {
        frame.content.mode('browse')
      }
    } catch (e) {
      /* ignore */
    }

    return true
  }

  function importFromUrl($root) {
    var $input = $root.find('.bandas-media-from-url__input')
    var $btn = $root.find('.bandas-media-from-url__btn')
    var $spinner = $root.find('.bandas-media-from-url__spinner')
    var url = $.trim($input.val() || '')

    if (!url) {
      setMessage($root, i18n.empty || 'Informe a URL da imagem.', 'error')
      $input.trigger('focus')
      return
    }

    $btn.prop('disabled', true)
    $spinner.addClass('is-active')
    setMessage($root, '')

    $.post(bandasMediaFromUrl.ajaxUrl, {
      action: bandasMediaFromUrl.action,
      nonce: bandasMediaFromUrl.nonce,
      url: url,
      post_id: getPostId()
    })
      .done(function (res) {
        if (!res || !res.success || !res.data || !res.data.attachment) {
          var msg =
            (res && res.data && res.data.message) ||
            i18n.error ||
            'Não foi possível importar a imagem.'
          setMessage($root, msg, 'error')
          return
        }

        setMessage($root, i18n.success || 'Imagem importada para a biblioteca.', 'success')
        $input.val('')

        var selected = selectInOpenFrame(res.data.attachment)
        if (!selected && bandasMediaFromUrl.libraryUrl) {
          window.location.href =
            bandasMediaFromUrl.libraryUrl +
            (bandasMediaFromUrl.libraryUrl.indexOf('?') >= 0 ? '&' : '?') +
            'item=' +
            encodeURIComponent(res.data.id)
        }
      })
      .fail(function (xhr) {
        var msg = i18n.error || 'Não foi possível importar a imagem.'
        try {
          var json = xhr.responseJSON
          if (json && json.data && json.data.message) {
            msg = json.data.message
          }
        } catch (e) {
          /* ignore */
        }
        setMessage($root, msg, 'error')
      })
      .always(function () {
        $btn.prop('disabled', false)
        $spinner.removeClass('is-active')
      })
  }

  $(document).on('click', '.bandas-media-from-url__btn', function (e) {
    e.preventDefault()
    importFromUrl($(this).closest('.bandas-media-from-url'))
  })

  $(document).on('keydown', '.bandas-media-from-url__input', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault()
      importFromUrl($(this).closest('.bandas-media-from-url'))
    }
  })
})(jQuery)
