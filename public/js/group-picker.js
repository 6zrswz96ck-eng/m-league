document.querySelectorAll('[data-group-form]').forEach((form) => {
    const boxes = [...form.querySelectorAll('input[name="players[]"]')];
    const count = form.querySelector('[data-selected-count]');
    const submit = form.querySelector('[data-submit]');

    const refresh = () => {
        const selected = boxes.filter((box) => box.checked).length;
        count.textContent = `（${selected} / 4人）`;
        submit.disabled = selected !== 4;
        boxes.forEach((box) => { box.disabled = selected >= 4 && !box.checked; });
    };

    form.addEventListener('change', refresh);
    form.addEventListener('submit', (event) => {
        if (boxes.filter((box) => box.checked).length !== 4) event.preventDefault();
    });
    refresh();
});
