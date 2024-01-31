# moodle-quiz-exportattemptscsv
A Quiz report plugin for Moodle to export the attempts history as a [CSV](https://en.wikipedia.org/wiki/Comma-separated_values) file

With this plugin every teacher should be able to export the attempts history of a quiz with a plenty of details directly in [CSV](https://en.wikipedia.org/wiki/Comma-separated_values) format.
The aim of this plugin is to let each teacher to analyze quiz attempts data with their personal tools, like a spreadsheet or other more powerful tools.

Normally Moodle let the teacher to show the attempt history of a single studente at a time and the information on the attempt could not be downloaded: with this plugin the teacher will have a new optional report named "Export Attempts history as CSV" in the "results" quiz administration menu.
The teacher will have some options on which information will be available into the file download (e.g question text, response, right answer or personal information) then selecting the students and clicking on "show report" the collected information will be downloaded to the teacher's computer.

Obviously, the report information could be critical data from privacy point of view, so teachers should be aware that the downloaded data should be adequately protected from stealing.


## Installation and set-up

This plugin should be compatible with Moodle 3.11+

### Install using git

Or you can install using git. Type this commands in the root of your Moodle install

    git clone https://github.com/rabser/moodle-quiz_exportattemptscsv.git mod/quiz/report/exportattemptscsv
    echo '/mod/quiz/report/exportattemptscsv/' >> .git/info/exclude
    
Then run the moodle update process
Site administration > Notifications
