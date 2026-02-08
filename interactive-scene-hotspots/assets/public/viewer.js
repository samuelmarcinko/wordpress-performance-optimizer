(function () {
  function initViewer(container) {
    const projectKey = container.dataset.project;
    const data = window.ISHProjects && window.ISHProjects[projectKey];
    if (!data || !data.scenes || !data.scenes.length) {
      return;
    }

    let currentScene = data.scenes[0];
    let tooltipVisible = false;
    let lastMouse = { x: 0, y: 0 };
    let rafId = null;

    container.innerHTML = `
      <div class="ish-viewer-inner">
        <div class="ish-image-wrapper">
          <img class="ish-image" alt="" />
          <svg class="ish-overlay" viewBox="0 0 1 1" preserveAspectRatio="none"></svg>
        </div>
        <div class="ish-tooltip" role="tooltip"></div>
        <div class="ish-modal" aria-hidden="true">
          <div class="ish-modal-content" role="dialog" aria-modal="true">
            <button class="ish-modal-close" type="button">×</button>
            <div class="ish-modal-body"></div>
          </div>
        </div>
      </div>
    `;

    const image = container.querySelector('.ish-image');
    const overlay = container.querySelector('.ish-overlay');
    const tooltip = container.querySelector('.ish-tooltip');
    const modal = container.querySelector('.ish-modal');
    const modalBody = container.querySelector('.ish-modal-body');
    const modalClose = container.querySelector('.ish-modal-close');

    modalClose.addEventListener('click', () => closeModal(modal));
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal(modal);
      }
    });

    function renderScene(scene) {
      currentScene = scene;
      image.src = scene.imageUrl || '';
      overlay.innerHTML = '';

      scene.hotspots.forEach((hotspot) => {
        const shape = createShape(hotspot);
        if (!shape) {
          return;
        }
        shape.dataset.id = hotspot.id;
        shape.dataset.label = hotspot.label || '';
        shape.dataset.tooltip = hotspot.tooltip || '';
        shape.dataset.action = JSON.stringify(hotspot.action || { type: 'noop' });
        shape.dataset.hover = JSON.stringify(hotspot.hover_style || {});
        shape.classList.add('ish-hotspot-shape');
        overlay.appendChild(shape);
      });
    }

    function createShape(hotspot) {
      if (hotspot.type === 'polygon') {
        const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        polygon.setAttribute('points', hotspot.coordinates.map((p) => `${p.x},${p.y}`).join(' '));
        return polygon;
      }
      if (hotspot.type === 'rect') {
        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', hotspot.coordinates.x);
        rect.setAttribute('y', hotspot.coordinates.y);
        rect.setAttribute('width', hotspot.coordinates.w);
        rect.setAttribute('height', hotspot.coordinates.h);
        return rect;
      }
      if (hotspot.type === 'circle') {
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', hotspot.coordinates.cx);
        circle.setAttribute('cy', hotspot.coordinates.cy);
        circle.setAttribute('r', hotspot.coordinates.r);
        return circle;
      }
      return null;
    }

    function showTooltip(text) {
      tooltip.innerHTML = text || '';
      tooltipVisible = !!text;
      tooltip.classList.toggle('is-visible', tooltipVisible);
      if (tooltipVisible && !rafId) {
        rafId = window.requestAnimationFrame(updateTooltipPosition);
      }
    }

    function updateTooltipPosition() {
      if (!tooltipVisible) {
        rafId = null;
        return;
      }
      tooltip.style.transform = `translate(${lastMouse.x + 12}px, ${lastMouse.y + 12}px)`;
      rafId = window.requestAnimationFrame(updateTooltipPosition);
    }

    function handleHotspotHover(event, entering) {
      const target = event.target.closest('.ish-hotspot-shape');
      if (!target) {
        return;
      }
      if (entering) {
        const hover = JSON.parse(target.dataset.hover || '{}');
        if (hover.fill) {
          target.style.fill = hover.fill;
        }
        if (hover.fillOpacity !== undefined) {
          target.style.fillOpacity = hover.fillOpacity;
        }
        if (hover.stroke) {
          target.style.stroke = hover.stroke;
        }
        const tooltipText = target.dataset.tooltip || target.dataset.label;
        showTooltip(tooltipText);
      } else {
        target.style.fill = '';
        target.style.stroke = '';
        target.style.fillOpacity = '';
        showTooltip('');
      }
    }

    function handleHotspotClick(event) {
      const target = event.target.closest('.ish-hotspot-shape');
      if (!target) {
        return;
      }
      const action = JSON.parse(target.dataset.action || '{"type":"noop"}');
      if (action.type === 'goto_scene') {
        const nextScene = data.scenes.find((scene) => scene.id === action.target_scene_id);
        if (nextScene) {
          transitionToScene(nextScene);
        }
      } else if (action.type === 'open_url' && action.url) {
        if (action.target_blank) {
          window.open(action.url, '_blank', 'noopener');
        } else {
          window.location.href = action.url;
        }
      } else if (action.type === 'open_modal') {
        openModal(modal, modalBody, action.modal_content || '');
      }
    }

    function transitionToScene(scene) {
      container.classList.add('is-transitioning');
      window.setTimeout(() => {
        renderScene(scene);
        container.classList.remove('is-transitioning');
      }, 250);
    }

    overlay.addEventListener('mouseover', (event) => handleHotspotHover(event, true));
    overlay.addEventListener('mouseout', (event) => handleHotspotHover(event, false));
    overlay.addEventListener('click', handleHotspotClick);

    container.addEventListener('mousemove', (event) => {
      const rect = container.getBoundingClientRect();
      lastMouse = { x: event.clientX - rect.left, y: event.clientY - rect.top };
    });

    renderScene(currentScene);
  }

  function openModal(modal, modalBody, content) {
    modalBody.innerHTML = content;
    modal.classList.add('is-visible');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal(modal) {
    modal.classList.remove('is-visible');
    modal.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ish-viewer').forEach((container) => {
      initViewer(container);
    });
  });
})();
