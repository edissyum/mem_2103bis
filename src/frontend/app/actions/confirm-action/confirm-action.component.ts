import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, exhaustMap, catchError, finalize } from 'rxjs/operators';
import { of } from 'rxjs';
import { Router } from '@angular/router';
import { FunctionsService } from '@service/functions.service';
import { SessionStorageService } from '@service/session-storage.service';

@Component({
    templateUrl: 'confirm-action.component.html',
    styleUrls: ['confirm-action.component.scss'],
})
export class ConfirmActionComponent implements OnInit {

    loading: boolean = false;
    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialogRef: MatDialogRef<ConfirmActionComponent>,
        private notify: NotificationService,
        private functions: FunctionsService,
        private router: Router,
        private sessionStorage: SessionStorageService,
        @Inject(MAT_DIALOG_DATA) public data: any
    ) { }

    ngOnInit(): void {
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
    }

    onSubmit() {
        this.loading = true;
        // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
        if (this.data.fromSearch) {
            this.dialogRef.close(this.data.resIds);
            return;
        }
        // END EDISSYUM - NCH01
        if (this.data.resIds.length === 0) {
            this.indexDocumentAndExecuteAction();
        } else {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    indexDocumentAndExecuteAction() {
        this.http.post('../rest/resources', this.data.resource).pipe(
            tap((data: any) => {
                this.data.resIds = [data.resId];
            }),
            exhaustMap(() => this.http.put(this.data.indexActionRoute, { resource: this.data.resIds[0], note: this.noteEditor !== undefined ? this.noteEditor.getNote() : '' })), // EDISSYUM - NCH01 Fix de l'action de confirmation si l'éditeur de note n'a pas chargé | Changer note: this.noteEditor.getNote() par note: this.noteEditor !== undefined ? this.noteEditor.getNote() : ''
            tap(() => {
                this.dialogRef.close(this.data.resIds);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                this.dialogRef.close();
                return of(false);
            })
        ).subscribe();
    }

    executeAction() {
        this.http.put(this.data.processActionRoute, { resources: this.data.resIds, note: this.noteEditor !== undefined ? this.noteEditor.getNote() : '' }).pipe( // EDISSYUM - NCH01 Fix de l'action de confirmation si l'éditeur de note n'a pas chargé | Changer note: this.noteEditor.getNote() par note: this.noteEditor !== undefined ? this.noteEditor.getNote() : ''
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
