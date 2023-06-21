import { Component, OnInit, Inject, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { SearchResultListComponent } from '@appRoot/search/result-list/search-result-list.component';

@Component({
    templateUrl: 'export-pesv2-action.component.html',
    styleUrls: ['export-pesv2-action.component.scss'],
})

export class ExportPESv2ActionComponent implements OnInit {
    loading: boolean = false;

    @ViewChild('noteEditor', { static: false }) noteEditor: NoteEditorComponent;
    @ViewChild('appSearchResultList', { static: false }) appSearchResultList: SearchResultListComponent;

    searchUrl: string = '';
    resourcesErrors: any[] = [];
    selectedRes: number[] = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public dialogRef: MatDialogRef<ExportPESv2ActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        public functions: FunctionsService
    ) { }

    ngOnInit(): void {}

    onSubmit() {
        this.loading = true;
        if (this.data.resIds.length > 0) {
            this.executeAction();
        }
    }

    executeAction() {
        this.http.put(this.data.processActionRoute, { resources: this.data.resIds, data: { basketId: this.data.basketId }}).pipe(
            tap((data: any) => {
                if (data !== null && !this.functions.empty(data.errors)) {
                    this.notify.error(data.errors);
                } else {
                    this.dialogRef.close(this.data.resIds);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }
}