(function() {
  var accordionButtons = document.getElementsByClassName('tracking-tool-tree-icon');
  var i, newIconSrc = null;

  function initialLoad(accordionButton) {
    var accordionButtonDisplay = 'none',
        accordionButtonAction = 'expand';

    const panel = accordionButton.parentElement.nextElementSibling.nextElementSibling;
    const percentLine = accordionButton.parentElement.nextElementSibling;

    if (localStorage.getItem(panel.id) === null) {
      localStorage.setItem(panel.id, 'true');
      accordionButtonDisplay = 'block';
      accordionButtonAction = 'collapse';
    } else if (localStorage.getItem(panel.id) === 'true') {
      accordionButtonDisplay = 'block';
      accordionButtonAction = 'collapse';
      newIconSrc = accordionButton.getAttribute('src').replace('tree_col', 'tree_exp');
      accordionButton.setAttribute('src', newIconSrc);
    } else {
      accordionButtonDisplay = 'none';
      accordionButtonAction = 'expand';
      newIconSrc = accordionButton.getAttribute('src').replace('tree_exp', 'tree_col');
      accordionButton.setAttribute('src', newIconSrc);
    }
    panel.style.display = accordionButtonDisplay;
    percentLine.style.display = accordionButtonDisplay;

    accordionButton.setAttribute('data-action', accordionButtonAction);
  }

  for (i = 0; i < accordionButtons.length; i++) {

    initialLoad(accordionButtons[i]);

    accordionButtons[i].addEventListener('click', function () {
      const triggeredPanel = this.parentElement.nextElementSibling.nextElementSibling;
      const triggeredPercentLine = this.parentElement.nextElementSibling;

      this.classList.toggle('tracking-tool-active');
      let action = this.getAttribute('data-action'),
          display = 'none';

      if (action === 'expand') {
        newIconSrc = this.getAttribute('src').replace('tree_col', 'tree_exp');
        action = 'collapse';
        display = 'block';
      } else {
        newIconSrc = this.getAttribute('src').replace('tree_exp', 'tree_col');
        action = 'expand';
        display = 'none';
      }

      if (newIconSrc != null) {
        this.setAttribute('src', newIconSrc);
        this.setAttribute('data-action', action);
      }

      triggeredPanel.style.display = display;
      triggeredPercentLine.style.display = display;

      if (triggeredPanel.style.display === 'block') {
        localStorage.setItem(triggeredPanel.id, 'true');
      } else {
        localStorage.setItem(triggeredPanel.id, 'false');
      }
    });
  }
})();