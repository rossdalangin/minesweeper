(function( $ ) {
    'use strict';

    $(function() {

        if (typeof quiz_sweeper_admin_ajax === 'undefined') {
            return;
        }

        var quizId = quiz_sweeper_admin_ajax.quiz_id;
        var ajaxUrl = quiz_sweeper_admin_ajax.ajax_url;
        var nonce = quiz_sweeper_admin_ajax.nonce;

        // Fetch existing questions on page load
        fetchQuestions();

        // Handle "Add a Question" button click
        $('#add-question-button').on('click', function() {
            showAddQuestionForm();
        });

        // Handle "Cancel" button click in the form
        $('#add-question-form-wrapper').on('click', '#cancel-add-question', function(e) {
            e.preventDefault();
            hideAddQuestionForm();
        });

        // Handle "Save Question" form submission
        $('#add-question-form-wrapper').on('submit', '#new-question-form', function(e) {
            e.preventDefault();
            saveQuestion();
        });

        // Handle "Delete Question" button click
        $('#questions-container').on('click', '.delete-question', function(e) {
            e.preventDefault();

            if ( ! confirm('Are you sure you want to delete this question?') ) {
                return;
            }

            var questionId = $(this).data('question-id');
            deleteQuestion(questionId);
        });

        function fetchQuestions() {
            $('#questions-container').html('<p>Loading questions...</p>');

            var data = {
                action: 'get_quiz_questions',
                nonce: nonce,
                quiz_id: quizId
            };

            $.get(ajaxUrl, data, function(response) {
                if (response.success) {
                    renderQuestions(response.data);
                } else {
                     $('#questions-container').html('<p>Could not load questions.</p>');
                }
            });
        }

        function renderQuestions(questions) {
            if (!questions || questions.length === 0) {
                $('#questions-container').html('<p>No questions yet. Add one below!</p>');
                return;
            }

            var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Question</th><th>Actions</th></tr></thead><tbody>';

            questions.forEach(function(q) {
                html += '<tr><td>' + q.title + '</td><td><button class="button delete-question" data-question-id="' + q.id + '">Delete</button></td></tr>';
            });

            html += '</tbody></table>';
            $('#questions-container').html(html);
        }

        function showAddQuestionForm() {
            var formHtml = `
                <form id="new-question-form" style="border: 1px solid #ccc; padding: 15px; margin-top: 15px;">
                    <h4>New Question</h4>
                    <p><input type="text" name="question_title" placeholder="Question Text" style="width: 100%;" /></p>
                    <p><strong>Choices (the first choice is the correct one by default):</strong></p>
                    <ol style="list-style-type: decimal; padding-left: 20px;">
                        <li><input type="text" name="choices[]" style="width: 80%"/> <input type="radio" name="correct_choice" value="0" checked /> Correct</li>
                        <li><input type="text" name="choices[]" style="width: 80%"/> <input type="radio" name="correct_choice" value="1" /> Correct</li>
                        <li><input type="text" name="choices[]" style="width: 80%"/> <input type="radio" name="correct_choice" value="2" /> Correct</li>
                        <li><input type="text" name="choices[]" style="width: 80%"/> <input type="radio" name="correct_choice" value="3" /> Correct</li>
                        <li><input type="text" name="choices[]" style="width: 80%"/> <input type="radio" name="correct_choice" value="4" /> Correct</li>
                    </ol>
                    <p>
                        <button type="submit" class="button button-primary">Save Question</button>
                        <button type="button" id="cancel-add-question" class="button">Cancel</button>
                    </p>
                </form>
            `;
            $('#add-question-form-wrapper').html(formHtml).show();
            $('#add-question-button').hide();
        }

        function hideAddQuestionForm() {
            $('#add-question-form-wrapper').hide().html('');
            $('#add-question-button').show();
        }

        function saveQuestion() {
            var questionData = {
                action: 'add_question_to_quiz',
                nonce: nonce,
                quiz_id: quizId,
                question_title: $('#new-question-form [name="question_title"]').val(),
                choices: $('#new-question-form [name="choices[]"]').map(function(){ return $(this).val(); }).get(),
                correct_choice: $('#new-question-form [name="correct_choice"]:checked').val()
            };

            if (!questionData.question_title) {
                alert('Please enter a question text.');
                return;
            }

            $.post(ajaxUrl, questionData, function(response) {
                if (response.success) {
                    hideAddQuestionForm();
                    // Instead of fetching all questions, just append the new one
                    var newQuestion = response.data;
                    var newRow = '<tr><td>' + newQuestion.title + '</td><td><button class="button delete-question" data-question-id="' + newQuestion.id + '">Delete</button></td></tr>';

                    // If this is the first question, we need to create the table
                    if ($('#questions-container').find('table').length === 0) {
                        var tableHtml = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Question</th><th>Actions</th></tr></thead><tbody>' + newRow + '</tbody></table>';
                        $('#questions-container').html(tableHtml);
                    } else {
                        $('#questions-container').find('tbody').append(newRow);
                    }

                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            });
        }

        function deleteQuestion(questionId) {
            var data = {
                action: 'delete_quiz_question',
                nonce: nonce,
                question_id: questionId
            };

            $.post(ajaxUrl, data, function(response) {
                if (response.success) {
                    fetchQuestions();
                } else {
                    alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            });
        }
    });

})( jQuery );
