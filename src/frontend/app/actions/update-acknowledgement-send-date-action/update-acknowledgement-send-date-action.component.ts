import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { Router } from '@angular/router';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'update-acknowledgement-send-date-action.component.html',
    styleUrls: ['../close-mail-action/close-mail-action.component.scss'],
})
export class UpdateAcknowledgementSendDateActionComponent implements OnInit {


    loading: boolean = false;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    acknowledgementSendDate: Date      = new Date();
    acknowledgementSendDateEnd: Date      = new Date();

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialogRef: MatDialogRef<UpdateAcknowledgementSendDateActionComponent>,
        public functions: FunctionsService,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private notify: NotificationService,
        private router: Router,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnInit(): void {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
    }

    onSubmit() {
        this.loading = true;
        if ( this.data.resIds.length > 0) {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    executeAction() {
        this.http.put(this.data.processActionRoute, {resources : this.data.resIds, note : this.noteEditor.getNote(), data : {send_date : (this.acknowledgementSendDate.getTime() / 1000).toString()}}).pipe(
            tap(() => {
                this.dialogRef.close(this.data.resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}
