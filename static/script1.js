// 3. JavaScript: Auto-Update Counts + Count-Up + Glow + Bounce

/**
 * Animates a numerical count-up effect for a given HTML element.
 * Adds a temporary glow effect during the animation.
 * @param {HTMLElement} element - The HTML element to update.
 * @param {number} start - The starting number for the animation.
 * @param {number} end - The target number for the animation.
 * @param {number} [duration=800] - The duration of the animation in milliseconds.
 */
function animateCountUp(element, start, end, duration = 800) {
    let startTime = null;
    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value;
        if (progress < 1) requestAnimationFrame(step);
    }
    element.classList.add('count-glow');
    requestAnimationFrame(step);
    setTimeout(() => element.classList.remove('count-glow'), duration + 200);
}

/**
 * Updates the summary counts based on the current task cards in the DOM.
 * Triggers a count-up animation for each updated number.
 */
function updateSummaryCounts() {
    const tasks = document.querySelectorAll('.task-card'); // Assumes your task items have this class
    let total = tasks.length, completed = 0, pending = 0, archived = 0;

    tasks.forEach(task => {
        const status = task.getAttribute('data-status'); // Assumes status is in a data-status attribute
        if (status === 'completed') completed++;
        else if (status === 'pending') pending++;
        else if (status === 'archived') archived++;
    });

    animateCountUp(document.getElementById('total-count'), parseInt(document.getElementById('total-count').textContent) || 0, total);
    animateCountUp(document.getElementById('completed-count'), parseInt(document.getElementById('completed-count').textContent) || 0, completed);
    animateCountUp(document.getElementById('pending-count'), parseInt(document.getElementById('pending-count').textContent) || 0, pending);
    animateCountUp(document.getElementById('archived-count'), parseInt(document.getElementById('archived-count').textContent) || 0, archived);
}

// Event listener for when tasks are updated (e.g., a task is added, deleted, or status changes)
// You would dispatch a custom event like this from your task management logic:
// document.dispatchEvent(new Event('taskUpdated'));
document.addEventListener('taskUpdated', updateSummaryCounts);

// Initial call to set counts when the page loads
document.addEventListener('DOMContentLoaded', updateSummaryCounts);